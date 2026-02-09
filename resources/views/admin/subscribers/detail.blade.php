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
</div>
@endsection
