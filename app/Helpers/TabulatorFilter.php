<?php

namespace App\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;


class TabulatorFilter
{
    public static function apply(Builder $query, Request $request, array $allowed): Builder
    {
        $filters = $request->input('filters', []);

        foreach ($filters as $filter) {
            $field = $filter['field'] ?? null;
            $value = $filter['value'] ?? null;

            if (!$field || $value === null) continue;
            if (!in_array($field, $allowed, true)) continue;

            $query->where($field, 'ILIKE', "%{$value}%");
        }

        return $query;
    }
}