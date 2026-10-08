<?php

declare(strict_types=1);

namespace Modules\Site\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Access\BelongsToHub;

/**
 * A message from the website's contact, employer or funder forms.
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
