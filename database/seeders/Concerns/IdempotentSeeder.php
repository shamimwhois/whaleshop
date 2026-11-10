<?php

namespace Database\Seeders\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait IdempotentSeeder
{
    /**
     * Insert the rows whose unique key is not present in the table yet.
     *
     * Most lookup tables in this project have no unique index on their natural
     * key, so insertOrIgnore() would still create duplicates. This filters in
     * PHP against the existing values instead.
     */
    protected function insertMissing(string $table, array $rows, string|array $uniqueBy): void
    {
        if ($rows === []) {
            return;
        }

        $uniqueBy  = (array) $uniqueBy;
        $existing  = [];

        foreach (DB::table($table)->get($uniqueBy) as $row) {
            $existing[$this->uniqueKey($row, $uniqueBy)] = true;
        }

        $rows = array_values(array_filter(
            $rows,
            fn (array $row) => ! isset($existing[$this->uniqueKey($row, $uniqueBy)])
        ));

        if ($rows !== []) {
            DB::table($table)->insert($rows);
        }
    }

    /**
     * Return the first matching record, creating it only when it is missing so
     * existing rows (and any edits made to them) are left untouched.
     */
    protected function createMissing(string $model, array $unique, array $values = []): Model
    {
        return $model::firstOrCreate($unique, $values);
    }

    private function uniqueKey(object|array $row, array $uniqueBy): string
    {
        return implode('|', array_map(
            fn (string $column) => (string) (is_array($row) ? ($row[$column] ?? '') : ($row->{$column} ?? '')),
            $uniqueBy
        ));
    }
}
