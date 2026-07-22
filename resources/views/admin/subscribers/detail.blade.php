@extends('layouts.admin')

@section('title', 'Subscriber Detail')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Subscriber Detail</h3>
        </div>
    </div>

    <div class="row">

        <div class="col-md-6">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-info"></i> Basic Information
                </div>

                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Email</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr>
                                    <td class="v-align-middle">{{ $subscriber->first_name }}</td>
                                    <td class="v-align-middle">{{ $subscriber->last_name }}</td>
                                    <td class="v-align-middle">{{ $subscriber->age }}</td>
                                    <td class="v-align-middle">
                                        @if($subscriber->gender == 1)
                                            Male
                                        @elseif($subscriber->gender == 2)
                                            Female
                                        @endif
                                    </td>
                                    <td class="v-align-middle">{{ $subscriber->email }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-info-circle"></i> Additional Information
                </div>

                <div class="panel-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Phone</th>
                                    <th>Nationality</th>
                                    <th>Japanese Level</th>
                                    <th>Selected Language</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr>
                                    <td class="v-align-middle">{{ $subscriber->phone }}</td>
                                    <td class="v-align-middle">{{ $subscriber->nationality }}</td>
                                    <td class="v-align-middle">N{{ $subscriber->japanese_level }}</td>
                                    <td class="v-align-middle">{{ $subscriber->user_selected_lang }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-tag"></i> Job Categories Selected
                </div>

                <div class="panel-body">
                    @if($categories->count())
                        {{ $categories->pluck('category')->implode(', ') }}
                    @else
                        <div class="alert alert-danger text-center">
                            No selected category found.
                        </div>
                    @endif
                </div>
            </div>

        </div>

        <div class="col-md-6">

            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-map-marker"></i> Locations Selected
                </div>

                <div class="panel-body">
                    @if(count($locations))
                        <ul style="list-style: none;">
                            @php $prefIndex = 0; $prefCount = count($locations); @endphp
                            @foreach($locations as $prefecture => $areas)
                                <li><b>{{ $prefecture }}</b></li>
                                <li>
                                    {{ implode(', ', $areas) }}
                                    @php $prefIndex++; @endphp
                                    @if($prefIndex < $prefCount)
                                        <hr>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="alert alert-danger text-center">
                            No selected location found.
                        </div>
                    @endif
                </div>
            </div>

        </div>

    </div>

    {{-- Enhanced Preferences --}}
    @if ($preferences)
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-sliders"></i> Enhanced Preferences
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <tr>
                            <td><b>Visa Type</b></td>
                            <td>{{ $preferences->visa_type ? ucwords(str_replace('_', ' ', $preferences->visa_type)) : '-' }}</td>
                        </tr>
                        <tr>
                            <td><b>Japanese Level</b></td>
                            <td>{{ $preferences->japanese_level ? ucfirst($preferences->japanese_level) : '-' }}</td>
                        </tr>
                        <tr>
                            <td><b>Max Hours/Week</b></td>
                            <td>{{ $preferences->max_hours_per_week ?? 'No limit' }}</td>
                        </tr>
                        <tr>
                            <td><b>Min Wage</b></td>
                            <td>{{ $preferences->min_wage ? '&yen;' . number_format($preferences->min_wage) . '+' : 'No preference' }}</td>
                        </tr>
                        <tr>
                            <td><b>Commute to Neighbors</b></td>
                            <td>{!! $preferences->commute_neighboring ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                        @if (!empty($enhancedAreas))
                        <tr>
                            <td><b>Preferred Areas</b></td>
                            <td>{{ implode(', ', $enhancedAreas) }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-money"></i> Payment & Schedule
                </div>
                <div class="panel-body">
                    <table class="table table-striped">
                        <tr>
                            <td><b>Monthly Transfer</b></td>
                            <td>{!! $preferences->wants_monthly_transfer ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                        <tr>
                            <td><b>Daily Payment</b></td>
                            <td>{!! $preferences->wants_daily_payment ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                        <tr>
                            <td><b>Hand Cash</b></td>
                            <td>{!! $preferences->wants_hand_cash ? '<span class="label label-success">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                    </table>

                    <h5><b>Shifts</b></h5>
                    <table class="table table-striped">
                        <tr>
                            <td>Any Time</td>
                            <td>{!! $preferences->shift_any ? '<i class="fa fa-check text-success"></i>' : '' !!}</td>
                        </tr>
                        <tr>
                            <td>Morning (6-12)</td>
                            <td>{!! $preferences->shift_morning ? '<i class="fa fa-check text-success"></i>' : '' !!}</td>
                        </tr>
                        <tr>
                            <td>Afternoon (12-18)</td>
                            <td>{!! $preferences->shift_afternoon ? '<i class="fa fa-check text-success"></i>' : '' !!}</td>
                        </tr>
                        <tr>
                            <td>Evening (18-24)</td>
                            <td>{!! $preferences->shift_evening ? '<i class="fa fa-check text-success"></i>' : '' !!}</td>
                        </tr>
                        <tr>
                            <td>Night (0-6)</td>
                            <td>{!! $preferences->shift_night ? '<i class="fa fa-check text-success"></i>' : '' !!}</td>
                        </tr>
                    </table>

                    <h5><b>Alerts</b></h5>
                    <table class="table table-striped">
                        <tr>
                            <td><b>Frequency</b></td>
                            <td>{{ ucfirst($preferences->alert_frequency) }}</td>
                        </tr>
                        <tr>
                            <td><b>Hand Cash Alert</b></td>
                            <td>{!! $preferences->alert_hand_cash ? '<span class="label label-warning">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                        <tr>
                            <td><b>High Wage Alert</b></td>
                            <td>{!! $preferences->alert_high_wage ? '<span class="label label-warning">Yes</span>' : '<span class="label label-default">No</span>' !!}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
