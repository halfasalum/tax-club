<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContentCategorySeeder extends Seeder
{
    /**
     * Starter learning categories (LRN-REQ04). Safe to re-run.
     */
    public function run(): void
    {
        $categories = [
            ['taxes-and-you', 'Kodi na Wewe', 'Taxes and you'],
            ['vat', 'Kodi ya Ongezeko la Thamani (VAT)', 'Value Added Tax (VAT)'],
            ['income-tax', 'Kodi ya Mapato', 'Income tax'],
            ['efd-receipts', 'Risiti na EFD', 'Receipts and EFDs'],
            ['customs', 'Forodha', 'Customs'],
            ['business-and-tin', 'Biashara na TIN', 'Business and TIN'],
            ['tax-careers', 'Ajira katika Kodi', 'Careers in tax'],
        ];

        foreach ($categories as $order => [$slug, $sw, $en]) {
            DB::table('content_categories')->updateOrInsert(['slug' => $slug], [
                'name_sw' => $sw,
                'name_en' => $en,
                'sort_order' => $order,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
