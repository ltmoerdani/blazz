<?php

namespace App\Exports;

use App\Models\ContactGroup;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactGroupsExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $contactgroups = ContactGroup::where('workspace_id', session()->get('current_workspace'))
            ->whereNull('deleted_at')
            ->get();


        // Modify the collection to include formatted phone numbers and group names
        return $contactgroups->map(function ($group) {
            return [
                'group_name' => $group->name,
            ];
        });
    }

    public function headings(): array
    {
        // Define your headers here
        return [
            'Group name'
        ];
    }
}
