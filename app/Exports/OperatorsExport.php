<?php

namespace App\Exports;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OperatorsExport implements FromQuery, WithHeadings, WithMapping, WithEvents, ShouldAutoSize, WithStyles
{
    // Days-out threshold for flagging a document as "expiring soon".
    private const EXPIRY_WARNING_DAYS = 30;

    public function query(): Builder
    {
        return Vehicle::query()
            ->with(['user', 'route_list'])
            ->whereHas('user', fn ($q) => $q->where('role', 'operator'))
            // Chunked exports page through this query with LIMIT/OFFSET, which
            // is only safe with a UNIQUE order. user_id alone isn't unique here
            // (one operator can own several vehicles), so id is a tie-breaker —
            // without it, rows can be silently duplicated or skipped on export.
            ->orderBy('user_id')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'Operator Name',
            'User Code',
            'Phone Number',
            'Email Address',
            'Address',
            'Vehicle Type',
            'Plate Number',
            'Driver Name',
            'Total Seats',
            'Assigned Route',
            'Has OR/CR',
            'OR/CR Expiry',
            'Has Franchise',
            'Franchise Expiry',
            'Document Status',
        ];
    }

    public function map($vehicle): array
    {
        return [
            $vehicle->user?->name,
            $vehicle->user?->user_code,
            $vehicle->user?->phone_number,
            $vehicle->user?->email_address,
            $vehicle->user?->address,
            $vehicle->vehicle_type,
            $vehicle->plate_number,
            $vehicle->driver_name,
            $vehicle->total_seats,
            $vehicle->route_list?->terminal ?? '—',
            $vehicle->has_or_cr ? 'Yes' : 'No',
            $vehicle->or_cr_expiry_date?->format('Y-m-d') ?? '—',
            $vehicle->has_franchise ? 'Yes' : 'No',
            $vehicle->franchise_expiry_date?->format('Y-m-d') ?? '—',
            $this->documentStatus($vehicle),
        ];
    }

    /**
     * Worst-case status across both OR/CR and franchise expiry dates.
     */
    private function documentStatus(Vehicle $vehicle): string
    {
        $dates = array_filter([$vehicle->or_cr_expiry_date, $vehicle->franchise_expiry_date]);

        if (empty($dates)) {
            return 'No documents on file';
        }

        $today = Carbon::today();
        $statuses = [];

        foreach ($dates as $date) {
            if ($date->lt($today)) {
                $statuses[] = 'expired';
            } elseif ($date->lte($today->copy()->addDays(self::EXPIRY_WARNING_DAYS))) {
                $statuses[] = 'expiring';
            } else {
                $statuses[] = 'valid';
            }
        }

        if (in_array('expired', $statuses)) return 'Expired';
        if (in_array('expiring', $statuses)) return 'Expiring Soon';
        return 'Valid';
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => ['font' => ['bold' => true], 'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1A1A2E'],
            ]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Header text color (white on the dark header fill set in styles()).
                $sheet->getStyle('A1:O1')->getFont()->getColor()->setRGB('FFFFFF');

                $highestRow = $sheet->getHighestRow();
                $statusColumn = 'O';

                for ($row = 2; $row <= $highestRow; $row++) {
                    $status = $sheet->getCell($statusColumn . $row)->getValue();

                    $color = match ($status) {
                        'Expired'       => 'F8D7DA', // soft red
                        'Expiring Soon' => 'FFF3CD', // soft amber
                        default         => null,
                    };

                    if ($color) {
                        $sheet->getStyle("A{$row}:O{$row}")
                            ->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()
                            ->setRGB($color);
                    }
                }

                // Freeze the header row for easier scrolling through long lists.
                $sheet->freezePane('A2');
            },
        ];
    }
}