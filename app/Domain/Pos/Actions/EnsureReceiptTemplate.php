<?php

namespace App\Domain\Pos\Actions;

use App\Models\ReceiptTemplate;

class EnsureReceiptTemplate
{
    public function handle(): ReceiptTemplate
    {
        $template = ReceiptTemplate::query()->where('is_active', true)->first();

        if ($template !== null) {
            return $template;
        }

        return ReceiptTemplate::query()->create([
            'header_text' => 'Restivo',
            'footer_text' => 'شكراً لزيارتكم',
            'paper_width' => 80,
            'show_tax' => true,
            'is_active' => true,
        ]);
    }
}
