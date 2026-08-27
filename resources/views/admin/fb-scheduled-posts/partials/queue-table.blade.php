@if (count($queue) > 0)
    <table class="table table-striped table-bordered fb-queue-table">
        <thead>
            <tr>
                <th style="width: 60px;">Photo</th>
                <th style="width: 60px;">Score</th>
                <th style="width: 300px;">Headline</th>
                <th style="width: 60px;">Format</th>
                <th>Station</th>
                <th>Category</th>
                <th>Wage</th>
                <th>Prefecture</th>
                <th style="width: 80px;">Status</th>
                <th style="width: 80px;">Expires</th>
                <th style="width: 120px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($queue as $item)
                <tr data-job-no="{{ $item->job->job_no }}">
                    <td class="text-center">
                        @if ($item->job->img_path && $item->job->img_name && $item->job->img_ext)
                            <img src="{{ url($item->job->img_path . $item->job->img_name . $item->job->img_ext) }}"
                                 alt="Job photo"
                                 style="max-width: 50px; max-height: 50px; object-fit: cover;">
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="score-badge" title="{{ implode(', ', $item->score_breakdown) }}">
                            {{ $item->score }}
                        </span>
                        <div class="score-breakdown">
                            @foreach ($item->score_breakdown as $point)
                                {{ $point }}<br>
                            @endforeach
                        </div>
                    </td>
                    <td>
                        <strong>{{ $item->headline }}</strong>
                        @if ($item->edited_since_posted)
                            <div class="edited-warning">
                                <i class="fa fa-exclamation-triangle"></i> Edited since posted — stale FB link
                            </div>
                        @endif
                    </td>
                    <td class="text-center">
                        <span class="label label-{{ $item->suggested_format === 'text' ? 'primary' : 'default' }}">
                            {{ strtoupper($item->suggested_format) }}
                        </span>
                    </td>
                    <td>{{ $item->job->station }}</td>
                    <td>{{ $item->job->category->english ?? 'N/A' }}</td>
                    <td>{{ $item->job->wage }}</td>
                    <td>{{ $item->job->prefecture_name }}</td>
                    <td>
                        @if ($item->is_affiliate)
                            <span class="affiliate-badge">AFFILIATE</span><br>
                        @endif
                        @if ($item->boost_eligible)
                            @php
                                $boostSpec = "Objective: LPV (Landing Page Views)\nBudget: ¥200-300/day\nDuration: +7 days from creation\nKill if >¥20/visitor at 48h\nFormat: Link/URL only";
                            @endphp
                            <span class="boost-badge" style="cursor: pointer;"
                                  data-boost-url="{{ $item->boost_url }}"
                                  data-boost-spec="{{ $boostSpec }}">
                                BOOST
                            </span>
                        @endif
                    </td>
                    <td class="text-center">
                        @if ($item->days_until_expiry < 10)
                            <span class="expires-warning">{{ $item->days_until_expiry }}d</span>
                        @else
                            <span>{{ $item->days_until_expiry }}d</span>
                        @endif
                    </td>
                    <td>
                        <button class="btn btn-xs btn-info copy-btn"
                                data-copy="{{ $item->headline }}&#10;&#10;{{ $item->post_url }}">
                            <i class="fa fa-copy"></i> Copy
                        </button>
                        <button class="btn btn-xs btn-success mark-btn"
                                data-job-no="{{ $item->job->job_no }}"
                                data-page="{{ $page }}"
                                data-format="{{ $item->suggested_format }}">
                            <i class="fa fa-check"></i> Posted
                        </button>
                        <a href="{{ url($item->job->detail_path) }}" target="_blank" class="btn btn-xs btn-default">
                            <i class="fa fa-external-link"></i>
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <div class="alert alert-info">
        No jobs match the current filters for this page.
    </div>
@endif
