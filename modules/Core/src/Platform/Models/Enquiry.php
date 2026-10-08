<?php

declare(strict_types=1);

namespace Modules\Core\Platform\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\BelongsToHub;

/**
 * A message from the website's contact, employer or funder forms (sent on the Site,
 * handled in the admin console).
 *
 * @property string $id
 * @property string $kind
 * @property string $name
 * @property string|null $organisation
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $topic
 * @property string|null $hub_id
 * @property string $message
 * @property string $status
 * @property CarbonImmutable|null $created_at
 */
final class Enquiry extends Model
{
    use BelongsToHub;
    use HasUlids;

    public const KINDS = ['contact', 'employer', 'funder'];

    public const TOPICS = ['general', 'sign_in', 'jobs', 'courses', 'business', 'privacy', 'hub', 'other'];

    protected $fillable = ['kind', 'name', 'organisation', 'phone', 'email', 'topic', 'hub_id', 'message', 'status', 'ip_hash'];

    protected $hidden = ['ip_hash'];
}
