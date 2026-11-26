<?php

// Save as: app/Services/OperatorImportService.php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\Notification;
use App\Models\RouteList;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Vehicle;
use App\Models\VehicleGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Imports operators and their vehicles from the terminal's old Excel records.
 *
 * Imported operators are records only: no password, so "No account"
 * (see User::hasAccount()). Because old records are often incomplete, the
 * rules here are the relaxed ones used for no-account operators in
 * edit-user-info; they are NOT the strict registration rules.
 *
 *  - parse():  read the file and validate every row (nothing is saved)
 *  - commit(): save the rows that passed, in one transaction
 */
class OperatorImportService
{
    /** Template columns, in order. */
    public const COLUMNS = [
        'operator_name', 'phone_number', 'email_address', 'address',
        'vehicle_type', 'route', 'plate_number', 'total_seats', 'group_number',
        'engine_number', 'body_number', 'chassis_number', 'driver_name',
        'franchise_expiry_date',
    ];

    /** Other headings people use in spreadsheets, mapped to our column names. */
    private const ALIASES = [
        'name' => 'operator_name', 'operator' => 'operator_name', 'full_name' => 'operator_name',
        'owner' => 'operator_name', 'owner_name' => 'operator_name',
        'phone' => 'phone_number', 'mobile' => 'phone_number', 'mobile_number' => 'phone_number',
        'contact' => 'phone_number', 'contact_number' => 'phone_number', 'contact_no' => 'phone_number',
        'email' => 'email_address',
        'type' => 'vehicle_type', 'vehicle' => 'vehicle_type',
        'terminal' => 'route', 'destination' => 'route',
        'plate' => 'plate_number', 'plate_no' => 'plate_number',
        'seats' => 'total_seats', 'seat_capacity' => 'total_seats', 'capacity' => 'total_seats',
        'group' => 'group_number',
        'engine' => 'engine_number', 'engine_no' => 'engine_number',
        'body' => 'body_number', 'body_no' => 'body_number',
        'chassis' => 'chassis_number', 'chassis_no' => 'chassis_number',
        'driver' => 'driver_name',
        'franchise_expiry' => 'franchise_expiry_date', 'expiry_date' => 'franchise_expiry_date',
        'franchise_expiration' => 'franchise_expiry_date',
    ];

    /** Rows for the downloadable template (one example). */
    public function templateRows(): array
    {
        return [[
            'Juan Dela Cruz', '09171234567', '', 'Zone 3, San Roque, Iriga City, Camarines Sur',
            'Bus', 'Naga', 'ABC 1234', 40, 1,
            'ENG-12345', 'BODY-12345', 'CHS-123456789', 'Pedro Santos',
            now()->addYear()->format('Y-m-d'),
        ]];
    }

    // ------------------------------------------------------------------
    // Step 1: read + validate (saves nothing)
    // ------------------------------------------------------------------

    /**
     * @return array<int, array{line:int, data:array, errors:array<int,string>}>
     */
    public function parse(string $path): array
    {
        $sheetRows = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);

        // Row 1 = headings.
        $headers = array_map(fn ($h) => $this->headingKey($h), array_shift($sheetRows) ?? []);

        $ctx = $this->buildContext();
        $rows = [];

        foreach ($sheetRows as $index => $cells) {
            if (collect($cells)->filter(fn ($v) => filled($v))->isEmpty()) {
                continue; // blank row
            }

            $raw = [];
            foreach ($headers as $i => $key) {
                if ($key !== '') {
                    $raw[$key] = $cells[$i] ?? null;
                }
            }

            [$data, $errors] = $this->validateRow($this->normalizeRow($raw), $ctx);

            $rows[] = [
                'line'   => $index + 2, // +1 for 0-index, +1 for the heading row
                'data'   => $data,
                'errors' => $errors,
            ];
        }

        return $rows;
    }

    /** Lookups loaded once per file, so validating 500 rows doesn't run 500 queries. */
    private function buildContext(): array
    {
        $routes = [];
        foreach (RouteList::with('operatorTicketRate')->get() as $routeList) {
            $rate = $routeList->operatorTicketRate;
            if (! $rate) {
                continue;
            }
            $routes[mb_strtolower(trim($rate->vehicle_type)) . '|' . mb_strtolower(trim($routeList->terminal))] = [
                'route_list_id' => $routeList->id,
                'vehicle_type'  => $rate->vehicle_type,
                'route'         => $routeList->terminal,
            ];
        }

        $operatorByPhone = [];
        $operatorByEmail = [];
        foreach (User::where('role', 'operator')->get(['id', 'phone_number', 'email_address']) as $user) {
            if (filled($user->phone_number)) {
                $operatorByPhone[$user->phone_number] = $user->id;
            }
            if (filled($user->email_address)) {
                $operatorByEmail[mb_strtolower($user->email_address)] = $user->id;
            }
        }

        $takenEmails = [];
        foreach (User::whereNotNull('email_address')->pluck('email_address') as $email) {
            $takenEmails[mb_strtolower($email)] = true;
        }

        $plates = [];
        foreach (Vehicle::pluck('plate_number') as $plate) {
            $plates[mb_strtoupper(trim((string) $plate))] = true;
        }

        return [
            'routes'          => $routes,
            'operatorByPhone' => $operatorByPhone,
            'operatorByEmail' => $operatorByEmail,
            'takenEmails'     => $takenEmails,
            'plates'          => $plates,      // plates already in the database
            'seenPlates'      => [],           // plates seen earlier in this file
            'emailOwner'      => [],           // email => operator_key within this file
        ];
    }

    private function headingKey($heading): string
    {
        $key = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', trim((string) $heading)), '_'));

        return self::ALIASES[$key] ?? $key;
    }

    private function normalizeRow(array $raw): array
    {
        $text = fn (string $key) => trim((string) ($raw[$key] ?? ''));

        $phone = $this->normalizePhone($text('phone_number'));
        $email = mb_strtolower($text('email_address'));
        $name  = preg_replace('/\s+/', ' ', $text('operator_name'));

        // Rows belong to the same operator when they share a phone, else an email, else a name.
        $operatorKey = $phone !== '' ? 'phone:' . $phone
            : ($email !== '' ? 'email:' . $email : 'name:' . mb_strtolower($name));

        return [
            'operator_key'          => $operatorKey,
            'operator_id'           => null,
            'operator_name'         => $name,
            'phone_number'          => $phone,
            'email_address'         => $email,
            'address'               => $text('address'),
            'vehicle_type'          => $text('vehicle_type'),
            'route'                 => $text('route'),
            'route_list_id'         => null,
            'plate_number'          => mb_strtoupper($text('plate_number')),
            'total_seats'           => $text('total_seats'),
            'group_number'          => $text('group_number') !== '' ? $text('group_number') : null,
            'engine_number'         => $text('engine_number'),
            'body_number'           => $text('body_number'),
            'chassis_number'        => $text('chassis_number'),
            'driver_name'           => $text('driver_name') ?: null,
            'franchise_expiry_date' => $this->normalizeDate($raw['franchise_expiry_date'] ?? null),
        ];
    }

    /** 09XXXXXXXXX, whether typed as +63..., 63..., 9XXXXXXXXX (Excel drops the 0) or with spaces/dashes. */
    private function normalizePhone(string $value): string
    {
        $p = preg_replace('/[^\d+]/', '', $value);

        if (str_starts_with($p, '+63')) {
            return '0' . substr($p, 3);
        }
        if (str_starts_with($p, '63') && strlen($p) === 12) {
            return '0' . substr($p, 2);
        }
        if (preg_match('/^9\d{9}$/', $p)) {
            return '0' . $p;
        }

        return $p;
    }

    /** Excel date cells (serial numbers) become Y-m-d; text must already be Y-m-d or it fails validation. */
    private function normalizeDate($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        if (is_numeric($value) && $value > 20000 && $value < 80000) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Throwable $e) {
                return (string) $value;
            }
        }

        return trim((string) $value);
    }

    /** @return array{0: array, 1: array<int,string>} */
    private function validateRow(array $row, array &$ctx): array
    {
        // Match vehicle type + route against the route list (case-insensitive) and use the official spelling.
        $routeKey = mb_strtolower($row['vehicle_type']) . '|' . mb_strtolower($row['route']);
        $routeMatch = $ctx['routes'][$routeKey] ?? null;

        if ($routeMatch) {
            $row['vehicle_type']  = $routeMatch['vehicle_type'];
            $row['route']         = $routeMatch['route'];
            $row['route_list_id'] = $routeMatch['route_list_id'];
        }

        // Relaxed rules for no-account operators. Only what the system truly needs is required.
        $validator = Validator::make($row, [
            'operator_name'         => 'required|string|min:2|max:255',
            'phone_number'          => ['nullable', 'regex:/^09\d{9}$/'],
            'email_address'         => 'nullable|email|max:255',
            'address'               => 'nullable|string|max:1000',
            'vehicle_type'          => 'required|string',
            'route'                 => 'required|string',
            'plate_number'          => 'required|string|max:50',
            'total_seats'           => 'required|integer|min:10|max:50',
            'group_number'          => 'required_if:vehicle_type,Bus,UV-express|nullable|integer|min:1|max:2',
            'engine_number'         => 'nullable|string|max:100',
            'body_number'           => 'nullable|string|max:100',
            'chassis_number'        => 'nullable|string|max:100',
            'driver_name'           => 'nullable|string|max:100',
            'franchise_expiry_date' => 'nullable|date_format:Y-m-d',
        ], [
            'phone_number.regex'                   => 'Mobile number must look like 09171234567.',
            'franchise_expiry_date.date_format'    => 'Franchise expiry must be a date (or YYYY-MM-DD).',
        ]);

        $errors = $validator->errors()->all();

        if ($row['vehicle_type'] !== '' && $row['route'] !== '' && ! $routeMatch) {
            $errors[] = "No route \"{$row['route']}\" exists for vehicle type \"{$row['vehicle_type']}\".";
        }

        // Plate numbers must be unique, in the database and within this file.
        if ($row['plate_number'] !== '') {
            if (isset($ctx['plates'][$row['plate_number']])) {
                $errors[] = 'Plate number already exists in the system.';
            } elseif (isset($ctx['seenPlates'][$row['plate_number']])) {
                $errors[] = 'Plate number appears more than once in this file.';
            }
            $ctx['seenPlates'][$row['plate_number']] = true;
        }

        // Which operator is this? Match an existing operator by phone, then email.
        $row['operator_id'] = ($row['phone_number'] !== '' ? ($ctx['operatorByPhone'][$row['phone_number']] ?? null) : null)
            ?? ($row['email_address'] !== '' ? ($ctx['operatorByEmail'][$row['email_address']] ?? null) : null);

        if (! $row['operator_id'] && $row['email_address'] !== '') {
            if (isset($ctx['takenEmails'][$row['email_address']])) {
                $errors[] = 'Email address is already used by another user.';
            }

            $owner = $ctx['emailOwner'][$row['email_address']] ?? null;
            if ($owner !== null && $owner !== $row['operator_key']) {
                $errors[] = 'Email address is used by another operator in this file.';
            }
            $ctx['emailOwner'][$row['email_address']] ??= $row['operator_key'];
        }

        return [$row, array_values(array_unique($errors))];
    }

    // ------------------------------------------------------------------
    // Step 2: save the rows that passed
    // ------------------------------------------------------------------

    /**
     * @param  array  $rows  the output of parse()
     * @return array{operators:int, vehicles:int, skipped:int}
     */
    public function commit(array $rows): array
    {
        $valid = collect($rows)->filter(fn ($r) => empty($r['errors']))->pluck('data');

        $operatorsCreated = 0;
        $vehiclesCreated  = 0;
        $skipped          = 0;

        DB::transaction(function () use ($valid, &$operatorsCreated, &$vehiclesCreated, &$skipped) {
            foreach ($valid->groupBy('operator_key') as $vehicles) {
                $first = $vehicles->first();

                $operator = $first['operator_id'] ? User::find($first['operator_id']) : null;

                if (! $operator) {
                    // No password on purpose: this operator has no account.
                    $operator = User::create([
                        'name'          => $first['operator_name'],
                        'email_address' => $first['email_address'] ?: null,
                        'phone_number'  => $first['phone_number'] ?: null,
                        'address'       => $first['address'] ?: null,
                        'role'          => 'operator',
                    ]);
                    $operatorsCreated++;
                }

                foreach ($vehicles as $row) {
                    // Someone may have added this plate since the preview.
                    if (Vehicle::where('plate_number', $row['plate_number'])->exists()) {
                        $skipped++;
                        continue;
                    }

                    $vehicle = $operator->vehicles()->create([
                        'route_list_id'         => $row['route_list_id'],
                        'vehicle_type'          => $row['vehicle_type'],
                        'plate_number'          => $row['plate_number'],
                        'total_seats'           => (int) $row['total_seats'],
                        'engine_number'         => $row['engine_number'] ?: null,
                        'body_number'           => $row['body_number'] ?: null,
                        'chassis_number'        => $row['chassis_number'] ?: null,
                        'driver_name'           => $row['driver_name'],
                        'has_franchise'         => filled($row['franchise_expiry_date']),
                        'franchise_expiry_date' => $row['franchise_expiry_date'] ?: null,
                    ]);

                    // Same grouping logic as manual registration.
                    if (in_array($row['vehicle_type'], ['Bus', 'UV-express']) && filled($row['group_number'])) {
                        $order = VehicleGroup::where('group_number', (int) $row['group_number'])
                            ->whereHas('vehicle', fn ($q) => $q->where('vehicle_type', $vehicle->vehicle_type))
                            ->max('order_number') + 1;

                        $vehicle->vehicle_group()->create([
                            'group_number' => (int) $row['group_number'],
                            'order_number' => $order,
                        ]);
                    }

                    $vehiclesCreated++;
                }
            }

            $summary = "Imported {$operatorsCreated} new operator(s) without an account and {$vehiclesCreated} vehicle(s) from a spreadsheet.";

            // One summary notification and audit entry per import, not one per row.
            $notification = Notification::create([
                'type'    => 'Registration',
                'title'   => 'Operators Imported',
                'message' => $summary,
            ]);

            UserNotification::create([
                'notification_id' => $notification->id,
                'user_id'         => auth()->id(),
            ]);

            app(AuditLogsService::class)->create([
                'user_id'  => auth()->id(),
                'action'   => 'Operators Imported',
                'subject'  => 'Admin imported operators and vehicles from a spreadsheet',
                'channel'  => 'Web',
                'metadata' => [
                    'ip_address' => request()->ip(),
                    'message'    => $summary,
                ],
            ]);
        });

        broadcast(new NotificationEvent());

        return ['operators' => $operatorsCreated, 'vehicles' => $vehiclesCreated, 'skipped' => $skipped];
    }
}