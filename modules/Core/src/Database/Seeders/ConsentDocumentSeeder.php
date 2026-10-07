<?php

declare(strict_types=1);

namespace Modules\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Core\Identity\Models\ConsentDocument;

/**
 * Reference data for every environment: version 1 of the terms of use and privacy
 * notice. PLACEHOLDER TEXT - replaced after Ku Tirhisana's legal review (Sprint 5).
 */
final class ConsentDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            'terms' => [
                'title' => 'Terms of use',
                'summary' => 'How you may use KasiHub, what we provide, and what we expect from you. Job seekers never pay.',
                'body' => "PLACEHOLDER - pending legal review.\n\nKasiHub is operated by Ku Tirhisana Consultancy (Pty) Ltd. By using KasiHub you agree to use it lawfully, to give true information, and not to misuse other people's information. Job seekers are never charged fees to find work. We may suspend accounts that are used for fraud or abuse.",
            ],
            'privacy' => [
                'title' => 'Privacy notice',
                'summary' => 'What personal information we collect, why, who can see it, and your rights under POPIA.',
                'body' => "PLACEHOLDER - pending legal review.\n\nWe collect only what we need to provide our services: your phone number, name, date of birth and the information you choose to add. We use it for the purposes you agree to. Employers, mentors and partners only see your details when you allow it. You can see, correct or ask us to delete your information at any time. Our Information Officer can be contacted through your hub.",
            ],
        ];

        foreach ($documents as $key => $document) {
            ConsentDocument::query()->firstOrCreate(
                ['key' => $key, 'version' => 1],
                [...$document, 'published_at' => now()->startOfDay()],
            );
        }
    }
}
