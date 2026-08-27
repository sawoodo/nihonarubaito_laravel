@extends('layouts.admin')

@section('title', 'Facebook Scheduled Posts')

@push('styles')
<style>
.fb-queue-table { font-size: 13px; }
.fb-queue-table th { background: #f5f5f5; font-weight: 600; }
.fb-queue-table td { vertical-align: middle; }
.score-badge { display: inline-block; padding: 4px 8px; border-radius: 4px; background: #007bff; color: white; font-weight: bold; }
.score-breakdown { font-size: 11px; color: #666; margin-top: 4px; }
.boost-badge { background: #28a745; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; font-weight: bold; }
.affiliate-badge { background: #17a2b8; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px; }
.expires-warning { color: #dc3545; font-weight: bold; }
.edited-warning { color: #ffc107; font-size: 11px; }
.copy-btn, .mark-btn { font-size: 11px; padding: 4px 8px; }
.quota-note { color: #666; font-size: 12px; font-style: italic; }
.nav-tabs { margin-bottom: 20px; }
.filters-panel { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
</style>
@endpush

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">
                Facebook Scheduled Posts
                <small class="quota-note pull-right">Daily quotas (guidance): Tokyo 8 · Kanto 6 · Osaka 8</small>
            </h3>
        </div>
    </div>

    @include('partials.admin.flash-message')

    {{-- Filters --}}
    <div class="filters-panel">
        <form method="GET" action="{{ route('admin.fb.index') }}" id="filter-form">
            <div class="row">
                <div class="col-md-2">
                    <label>Category</label>
                    <select name="categories[]" class="form-control" multiple size="5">
                        <option value="1" {{ in_array(1, $filters['categories']) ? 'selected' : '' }}>Packing/Sorting</option>
                        <option value="2" {{ in_array(2, $filters['categories']) ? 'selected' : '' }}>Restaurant</option>
                        <option value="3" {{ in_array(3, $filters['categories']) ? 'selected' : '' }}>Convenience Store</option>
                        <option value="4" {{ in_array(4, $filters['categories']) ? 'selected' : '' }}>Bed Making/Cleaning</option>
                        <option value="5" {{ in_array(5, $filters['categories']) ? 'selected' : '' }}>Delivery</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>Wage Floor</label>
                    <select name="wage_floor" class="form-control">
                        <option value="">All</option>
                        <option value="1300" {{ $filters['wage_floor'] == 1300 ? 'selected' : '' }}>≥¥1,300</option>
                        <option value="1400" {{ $filters['wage_floor'] == 1400 ? 'selected' : '' }}>≥¥1,400</option>
                        <option value="1500" {{ $filters['wage_floor'] == 1500 ? 'selected' : '' }}>≥¥1,500</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="hook_only" value="1" {{ $filters['hook_only'] ? 'checked' : '' }}>
                            Payment Hook Only
                        </label>
                    </div>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="affiliate_only" value="1" {{ $filters['affiliate_only'] ? 'checked' : '' }}>
                            Affiliate Only
                        </label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="boost_only" value="1" {{ $filters['boost_only'] ? 'checked' : '' }}>
                            Boost Eligible Only
                        </label>
                    </div>
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary btn-block">Apply Filters</button>
                    <a href="{{ route('admin.fb.index') }}" class="btn btn-default btn-block">Reset</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Tabs --}}
    <ul class="nav nav-tabs" role="tablist">
        <li role="presentation" class="active">
            <a href="#tokyo" aria-controls="tokyo" role="tab" data-toggle="tab">
                Tokyo <span class="badge">{{ count($tokyoQueue) }}</span>
            </a>
        </li>
        <li role="presentation">
            <a href="#kanto" aria-controls="kanto" role="tab" data-toggle="tab">
                Kanto <span class="badge">{{ count($kantoQueue) }}</span>
            </a>
        </li>
        <li role="presentation">
            <a href="#osaka" aria-controls="osaka" role="tab" data-toggle="tab">
                Osaka <span class="badge">{{ count($osakaQueue) }}</span>
            </a>
        </li>
        <li role="presentation">
            <a href="#support" aria-controls="support" role="tab" data-toggle="tab">
                Supporting Panels
            </a>
        </li>
    </ul>

    {{-- Tab Content --}}
    <div class="tab-content">

        {{-- Tokyo --}}
        <div role="tabpanel" class="tab-pane active" id="tokyo">
            <div class="pull-right" style="margin-bottom: 10px;">
                <a href="{{ route('admin.fb.export', array_merge($filters, ['page' => 'tokyo'])) }}" class="btn btn-sm btn-success">
                    <i class="fa fa-download"></i> Export Tokyo Queue
                </a>
            </div>
            <div class="clearfix"></div>
            @include('admin.fb-scheduled-posts.partials.queue-table', ['queue' => $tokyoQueue, 'page' => 'tokyo'])
        </div>

        {{-- Kanto --}}
        <div role="tabpanel" class="tab-pane" id="kanto">
            <div class="pull-right" style="margin-bottom: 10px;">
                <a href="{{ route('admin.fb.export', array_merge($filters, ['page' => 'kanto'])) }}" class="btn btn-sm btn-success">
                    <i class="fa fa-download"></i> Export Kanto Queue
                </a>
            </div>
            <div class="clearfix"></div>
            @include('admin.fb-scheduled-posts.partials.queue-table', ['queue' => $kantoQueue, 'page' => 'kanto'])
        </div>

        {{-- Osaka --}}
        <div role="tabpanel" class="tab-pane" id="osaka">
            <div class="pull-right" style="margin-bottom: 10px;">
                <a href="{{ route('admin.fb.export', array_merge($filters, ['page' => 'osaka'])) }}" class="btn btn-sm btn-success">
                    <i class="fa fa-download"></i> Export Osaka Queue
                </a>
            </div>
            <div class="clearfix"></div>
            @include('admin.fb-scheduled-posts.partials.queue-table', ['queue' => $osakaQueue, 'page' => 'osaka'])
        </div>

        {{-- Supporting Panels --}}
        <div role="tabpanel" class="tab-pane" id="support">
            <h4>Site-Only Jobs (last 48h)</h4>
            <p class="text-muted">New published jobs in prefectures not assigned to any Facebook page.</p>
            @if (count($siteOnlyJobs) > 0)
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Prefecture</th>
                            <th>Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($siteOnlyJobs as $prefecture => $jobs)
                            <tr>
                                <td>{{ $prefecture }}</td>
                                <td>{{ count($jobs) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted">No site-only jobs in the last 48 hours.</p>
            @endif

            <hr>

            <h4>Sourcing Gaps</h4>
            <p class="text-muted">Station×Category combos with ≥3 affiliate applies but zero live affiliate inventory.</p>
            @if (count($sourcingGaps) > 0)
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Station</th>
                            <th>Category</th>
                            <th>Applies (120d)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sourcingGaps as $gap)
                            <tr>
                                <td>{{ $gap->station }}</td>
                                <td>{{ $gap->category_name }}</td>
                                <td>{{ $gap->applies }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-muted">No sourcing gaps detected.</p>
            @endif
        </div>

    </div>

</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Copy to clipboard
    $('.copy-btn').on('click', function() {
        const text = $(this).data('copy');
        navigator.clipboard.writeText(text).then(() => {
            const $btn = $(this);
            const originalText = $btn.html();
            $btn.html('<i class="fa fa-check"></i> Copied');
            setTimeout(() => $btn.html(originalText), 2000);
        });
    });

    // Mark as posted
    $('.mark-btn').on('click', function() {
        if (!confirm('Mark this job as posted?')) return;

        const $btn = $(this);
        const jobNo = $btn.data('job-no');
        const page = $btn.data('page');
        const format = $btn.data('format');

        $.ajax({
            url: '{{ route('admin.fb.markPosted') }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                job_no: jobNo,
                page: page,
                format: format,
                was_boosted: false
            },
            success: function() {
                $btn.closest('tr').fadeOut();
                alert('Job marked as posted');
            },
            error: function(xhr) {
                alert('Error: ' + (xhr.responseJSON?.error || 'Unknown error'));
            }
        });
    });

    // Show boost URL
    $('.boost-badge').on('click', function() {
        const url = $(this).data('boost-url');
        const spec = $(this).data('boost-spec');
        alert('Boost URL:\n' + url + '\n\nSpec:\n' + spec);
    });
});
</script>
@endpush
