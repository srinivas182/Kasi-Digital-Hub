@if ($cv['summary'])
    <h2>Profile</h2>
    <p>{{ $cv['summary'] }}</p>
@endif

@if ($cv['experience'] !== [])
    <h2>Experience</h2>
    @foreach ($cv['experience'] as $item)
        <div class="item">
            <div class="item-head">
                <span class="item-title">{{ $item['title'] }}@if ($item['organisation']), {{ $item['organisation'] }}@endif</span>
                <span class="muted">{{ $item['period'] }}</span>
            </div>
            @if ($item['place'])<div class="muted">{{ $item['place'] }}</div>@endif
            @if ($item['bullets'] !== [])
                <ul>@foreach ($item['bullets'] as $bullet)<li>{{ $bullet }}</li>@endforeach</ul>
            @endif
        </div>
    @endforeach
@endif

@if ($cv['education'] !== [])
    <h2>Education</h2>
    @foreach ($cv['education'] as $item)
        <div class="item">
            <div class="item-head">
                <span class="item-title">{{ $item['name'] }}@if ($item['verified'])<span class="verified">&#10003; Verified</span>@endif</span>
                <span class="muted">{{ $item['inProgress'] ? 'In progress' : $item['year'] }}</span>
            </div>
            @if ($item['institution'])<div class="muted">{{ $item['institution'] }}</div>@endif
            @if ($item['details'])<div>{{ $item['details'] }}</div>@endif
        </div>
    @endforeach
@endif

@if ($cv['skills'] !== [])
    <h2>Skills</h2>
    <div class="tags">@foreach ($cv['skills'] as $skill)<span>{{ $skill }}</span>@endforeach</div>
@endif

@if ($cv['languages'] !== [] || $cv['licence'] || $cv['ownTransport'])
    <h2>Languages and other</h2>
    @if ($cv['languages'] !== [])
        <p>@foreach ($cv['languages'] as $l){{ $l['language'] }} ({{ $l['level'] }})@if (! $loop->last), @endif @endforeach</p>
    @endif
    @if ($cv['licence'])<p>Driver's licence: code {{ $cv['licence'] }}@if ($cv['ownTransport']) - own transport @endif</p>@elseif ($cv['ownTransport'])<p>Own transport</p>@endif
@endif
