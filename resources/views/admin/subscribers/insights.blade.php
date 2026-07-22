@extends('layouts.admin')

@section('title', 'Subscriber Insights')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Subscriber Insights</h3>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row">
        <div class="col-md-3">
            <div class="panel panel-primary">
                <div class="panel-heading">Total Subscribers</div>
                <div class="panel-body text-center" style="font-size: 2em; font-weight: bold;">
                    {{ number_format($stats['total_subscribers']) }}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-success">
                <div class="panel-heading">With Enhanced Preferences</div>
                <div class="panel-body text-center" style="font-size: 2em; font-weight: bold;">
                    {{ number_format($stats['with_preferences']) }}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-warning">
                <div class="panel-heading">Want Hand Cash</div>
                <div class="panel-body text-center" style="font-size: 2em; font-weight: bold;">
                    {{ number_format($stats['wants_hand_cash']) }}
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="panel panel-info">
                <div class="panel-heading">Want Daily Payment</div>
                <div class="panel-body text-center" style="font-size: 2em; font-weight: bold;">
                    {{ number_format($stats['wants_daily_payment']) }}
                </div>
            </div>
        </div>
    </div>

    <div class="row">

        {{-- Visa Breakdown --}}
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-id-card"></i> Visa Type Breakdown
                </div>
                <div class="panel-body">
                    @if ($stats['visa_breakdown']->count())
                        <table class="table table-striped">
                            <thead><tr><th>Visa Type</th><th>Count</th></tr></thead>
                            <tbody>
                                @foreach ($stats['visa_breakdown'] as $row)
                                    <tr>
                                        <td>{{ ucwords(str_replace('_', ' ', $row->visa_type)) }}</td>
                                        <td>{{ $row->count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted text-center">No data yet</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Japanese Level Breakdown --}}
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-language"></i> Japanese Level Breakdown
                </div>
                <div class="panel-body">
                    @if ($stats['japanese_breakdown']->count())
                        <table class="table table-striped">
                            <thead><tr><th>Level</th><th>Count</th></tr></thead>
                            <tbody>
                                @foreach ($stats['japanese_breakdown'] as $row)
                                    <tr>
                                        <td>{{ ucfirst($row->japanese_level) }}</td>
                                        <td>{{ $row->count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-muted text-center">No data yet</p>
                    @endif
                </div>
            </div>
        </div>

    </div>

    <div class="row">

        {{-- Shift Preferences --}}
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-clock-o"></i> Shift Preferences
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead><tr><th>Shift</th><th>Count</th></tr></thead>
                        <tbody>
                            <tr><td>Morning (6-12)</td><td>{{ $stats['shift_morning'] }}</td></tr>
                            <tr><td>Afternoon (12-18)</td><td>{{ $stats['shift_afternoon'] }}</td></tr>
                            <tr><td>Evening (18-24)</td><td>{{ $stats['shift_evening'] }}</td></tr>
                            <tr><td>Night (0-6)</td><td>{{ $stats['shift_night'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Alert Frequency --}}
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-bell"></i> Alert Frequency
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <thead><tr><th>Frequency</th><th>Count</th></tr></thead>
                        <tbody>
                            <tr><td>Immediate</td><td>{{ $stats['alert_immediate'] }}</td></tr>
                            <tr><td>Daily</td><td>{{ $stats['alert_daily'] }}</td></tr>
                            <tr><td>Weekly</td><td>{{ $stats['alert_weekly'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>
@endsection
