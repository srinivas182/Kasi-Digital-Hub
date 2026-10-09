<?php

declare(strict_types=1);

namespace Modules\Work\Search;

use App\Support\Format\SaFormat;
use Modules\Core\Search\SearchDocument;
use Modules\Core\Search\SearchSource;
use Modules\Work\Models\JobListing;

/** Live job listings, findable by signed-in adults (KasiWork is adults only). */
final class JobSearchSource implements SearchSource
{
    public function type(): string
    {
        return 'job';
    }

    public function all(): iterable
    {
        foreach (JobListing::query()->live()->with(['organisation', 'municipality', 'occupation'])->cursor() as $listing) {
            yield $this->document($listing);
        }
    }

    public function find(string $ref): ?SearchDocument
    {
        $listing = JobListing::query()->live()->with(['organisation', 'municipality', 'occupation'])->whereKey($ref)->first();

        return $listing === null ? null : $this->document($listing);
    }

    private function document(JobListing $l): SearchDocument
    {
        $where = implode(', ', array_filter([$l->place_name, $l->municipality?->name]));

        return new SearchDocument('job', $l->id, $l->title.' - '.$l->organisation->displayName(),
            trim($where.'. '.$l->occupation?->title.'. '.SaFormat::money($l->pay_min_cents).' per '.$l->pay_period.'. '.mb_substr($l->description, 0, 400)),
            '/work/jobs/'.$l->id, 'members');
    }
}
