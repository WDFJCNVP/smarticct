<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $queueStatuses = ['staging', 'loading', 'departed'];
    private array $slotStatuses  = ['waiting', 'queued', 'departed'];

    public function up(): void
    {
        $this->setStatuses('queues', [...$this->queueStatuses, 'skipped']);
        $this->setStatuses('daily_schedule_slots', [...$this->slotStatuses, 'skipped'], 'waiting');
    }

    public function down(): void
    {
        DB::table('queues')->where('status', 'skipped')->update(['status' => 'departed']);
        DB::table('daily_schedule_slots')->where('status', 'skipped')->update(['status' => 'departed']);

        $this->setStatuses('queues', $this->queueStatuses);
        $this->setStatuses('daily_schedule_slots', $this->slotStatuses, 'waiting');
    }

    private function setStatuses(string $table, array $values, ?string $default = null): void
    {
        $driver = DB::getDriverName();
        $list   = implode(',', array_map(fn ($v) => "'{$v}'", $values));

        if (in_array($driver, ['mysql', 'mariadb'])) {
            $defaultSql = $default ? " DEFAULT '{$default}'" : '';
            DB::statement("ALTER TABLE {$table} MODIFY status ENUM({$list}) NOT NULL{$defaultSql}");

            return;
        }

        if ($driver === 'pgsql') {
            // Laravel's enum on Postgres is a varchar with a CHECK constraint. Replace that constraint.
            $constraints = DB::select(
                "select conname from pg_constraint
                 where conrelid = '{$table}'::regclass and contype = 'c'
                 and pg_get_constraintdef(oid) ilike '%status%'"
            );

            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT \"{$constraint->conname}\"");
            }

            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_status_check CHECK (status::text IN ({$list}))");
        }
    }
};