<?php

namespace App\Services;

class ThermalReceiptService
{
    // ESC/POS Byte Commands
    const ESC = "\x1b";
    const GS  = "\x1d";

    public static function printQueueSlip(array $data): bool
    {
        // 1. Initialize printer & select Hardware Font A (The Self-Test Font)
        $receipt = self::ESC . "@";                  // Initialize
        $receipt .= self::ESC . "M" . "\x00";        // Select Font A (12x24 hardware font)
        
        // 2. Header (Centered, Double Height + Bold)
        $receipt .= self::ESC . "a" . "\x01";        // Center align
        $receipt .= self::ESC . "E" . "\x01";        // Bold ON
        $receipt .= self::GS . "!" . "\x10";         // Double height text
        $receipt .= "SMART ICCT\n";
        $receipt .= self::GS . "!" . "\x00";         // Normal size
        $receipt .= "QUEUED VEHICLE SLIP\n";
        $receipt .= self::ESC . "E" . "\x00";        // Bold OFF
        $receipt .= "--------------------------------\n"; // Exactly 32 chars

        // 3. Details (Left aligned, native 32-char width table)
        $receipt .= self::ESC . "a" . "\x00";        // Left align
        $receipt .= self::makeRow("Ref:", $data['reference_no']);
        $receipt .= self::makeRow("Date:", $data['date']);
        $receipt .= self::makeRow("Operator:", substr($data['operator_name'], 0, 20));
        $receipt .= self::makeRow("Driver:", substr($data['driver_name'] ?: 'N/A', 0, 20));
        
        // Bold Plate Number
        $receipt .= self::ESC . "E" . "\x01";        // Bold ON
        $receipt .= self::makeRow("Plate:", $data['plate_number']);
        $receipt .= self::ESC . "E" . "\x00";        // Bold OFF
        
        $receipt .= self::makeRow("Type:", $data['vehicle_type']);
        $receipt .= self::makeRow("To:", substr($data['destination'], 0, 22));

        // 4. Totals
        $receipt .= "--------------------------------\n";
        $receipt .= self::ESC . "E" . "\x01";        // Bold ON
        $receipt .= self::makeRow("Queue Fee:", "PHP " . number_format($data['fee'], 2));
        $receipt .= self::ESC . "E" . "\x00";        // Bold OFF

        if ($data['is_cash']) {
            $receipt .= self::makeRow("Cash Paid:", "PHP " . number_format($data['amount_received'], 2));
            $receipt .= self::ESC . "E" . "\x01";
            $receipt .= self::makeRow("Change:", "PHP " . number_format($data['change'], 2));
            $receipt .= self::ESC . "E" . "\x00";
        }

        // 5. Footer & Paper Feed
        $receipt .= "--------------------------------\n";
        $receipt .= self::ESC . "a" . "\x01";        // Center align
        $receipt .= "KEEP THIS TICKET\n";
        $receipt .= "\n\n\n\n";                      // Feed paper forward so it clears the cutter

        // 6. Send raw bytes directly to the Windows shared printer spooler
        $printerPath = "\\\\127.0.0.1\\POS58";

        try {
            $handle = fopen($printerPath, "wb");
            if ($handle) {
                fwrite($handle, $receipt);
                fclose($handle);
                return true;
            }
        } catch (\Exception $e) {
            \Log::error("Thermal Print Error: " . $e->getMessage());
        }

        return false;
    }

    // Helper to pad columns into a neat 32-character line
    private static function makeRow(string $left, string $right, int $width = 32): string
    {
        $spaces = max(1, $width - strlen($left) - strlen($right));
        return $left . str_repeat(' ', $spaces) . $right . "\n";
    }
}