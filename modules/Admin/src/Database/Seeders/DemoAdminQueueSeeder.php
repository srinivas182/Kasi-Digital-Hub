<?php

declare(strict_types=1);

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Database\Seeders\DemoDocumentsSeeder;
use Modules\Core\Documents\Models\Document;
use Modules\Core\Identity\Models\User;
use Modules\Core\Platform\Models\Enquiry;
use Modules\Core\Structure\Models\Hub;
use Modules\Core\Structure\Models\Municipality;
use Modules\Core\Structure\Models\Organisation;

/**
 * Work for the admin console demo: ~20 documents awaiting review, 3 organisations awaiting
 * verification and 10 website enquiries. All fictitious.
 */
final class DemoAdminQueueSeeder extends Seeder
{
    private const TYPES = ['id_document', 'matric_certificate', 'qualification', 'proof_of_address', 'cipc_certificate'];

    public function run(): void
    {
        $this->documents();
        $this->organisations();
        $this->enquiries();
    }

    private function documents(): void
    {
        if (Document::query()->where('original_name', 'like', 'queue-%')->exists()) {
            return;
        }

        $disk = (string) config('kasi.documents.disk');
        $people = User::query()->where('phone', 'like', '+2784000%')->orderBy('phone')->limit(20)->get();

        foreach ($people as $i => $person) {
            $type = self::TYPES[$i % count(self::TYPES)];
            $contents = DemoDocumentsSeeder::dummyPdf("DEMO DOCUMENT - NOT REAL\n{$type}\n{$person->fullName()}");
            $path = sprintf('%s/%s.pdf', $person->id, Str::ulid());
            Storage::disk($disk)->put($path, $contents);

            Document::query()->create([
                'user_id' => $person->id, 'type' => $type, 'disk' => $disk, 'path' => $path,
                'original_name' => "queue-{$type}.pdf", 'mime_type' => 'application/pdf', 'size_bytes' => strlen($contents),
                'sha256' => hash('sha256', $contents), 'status' => Document::UPLOADED,
            ])->forceFill(['created_at' => now()->subHours(3 + $i * 7)])->save();
        }
    }

    private function organisations(): void
    {
        $pending = [
            ['employer', 'Giyani Spar Express (demo)', '2021/118843/07', 'LIM331'],
            ['training_provider', 'Ekasi Coding Academy (demo)', '2020/554210/08', 'JHB'],
            ['partner', 'Ubuntu Micro-Lenders (demo)', '2018/201992/07', 'ETH'],
        ];

        foreach ($pending as [$type, $name, $reg, $city]) {
            $org = Organisation::query()->firstOrCreate(['name' => $name], [
                'type' => $type, 'registration_number' => $reg, 'verification_status' => 'pending',
                'contact_email' => Str::slug($name).'@example.co.za',
                'municipality_id' => Municipality::query()->where('code', $city)->value('id'),
            ]);

            if ($org->wasRecentlyCreated) {
                $member = User::query()->where('phone', 'like', '+2784000%')->inRandomOrder()->first();
                if ($member !== null) {
                    $org->members()->syncWithoutDetaching([$member->id => ['title' => 'Owner']]);
                }
            }
        }
    }

    private function enquiries(): void
    {
        if (Enquiry::query()->exists()) {
            return;
        }

        $hub = Hub::query()->where('code', 'LP-GIY-TSU')->value('id');
        $samples = [
            ['contact', 'Ayanda Dlamini', null, 'sign_in', 'I changed my phone number and cannot sign in anymore. Please help.'],
            ['contact', 'Mpho Sithole', null, 'hub', 'When will the Inanda hub open? Many of us are waiting.'],
            ['employer', 'Johan van Wyk', 'Tzaneen Citrus Packers (demo)', null, 'We need 40 seasonal packers from May. Can we post through KasiHub?'],
            ['employer', 'Lindiwe Zulu', 'Umlazi Auto Repairs (demo)', null, 'Looking for two apprentice mechanics near Umlazi.'],
            ['funder', 'Rethabile Mokoena', 'Sample Retail SETA programme', null, 'We would like to sponsor 200 learners on the retail learnership.'],
            ['funder', 'Grace Naidoo', 'Kasi Futures Foundation (sample)', null, 'Can we receive monthly impact reports for Soweto?'],
            ['contact', 'Sibusiso Khoza', null, 'privacy', 'How do I delete my old account?'],
            ['contact', 'Tshepo Maluleke', null, 'jobs', 'Are there jobs for people without matric?'],
            ['contact', 'Nandi Mahlangu', null, 'courses', 'Do you have a course on bookkeeping for small businesses?'],
            ['employer', 'Thabo Ndlovu', 'Ndlovu Construction (demo)', null, 'We want to hire 10 general workers in Katlehong.'],
        ];

        foreach ($samples as $i => [$kind, $name, $organisation, $topic, $message]) {
            Enquiry::query()->create([
                'kind' => $kind, 'name' => $name, 'organisation' => $organisation, 'topic' => $topic,
                'email' => Str::slug($name).'@example.co.za', 'hub_id' => $i === 1 ? null : ($i % 3 === 0 ? $hub : null),
                'message' => $message, 'status' => $i > 7 ? 'handled' : 'new',
            ])->forceFill(['created_at' => now()->subDays($i)])->save();
        }
    }
}
