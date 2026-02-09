@extends('layouts.admin')

@section('title', 'Subscribers')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Subscribers</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">

            @if(session('success'))
                <div class="alert alert-success text-center">
                    {{ session('success') }} status: ok
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-condensed table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>First Name</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Nationality</th>
                            <th>Japanese Level</th>
                            <th>Selected Language</th>
                            <th>Categories</th>
                            <th>Locations</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @if($subscribers->count())
                            @foreach($subscribers as $subscriber)
                                <tr>
                                    <td class="v-align-middle">
                                        {{ $subscriber->first_name }} {{ $subscriber->last_name }}
                                    </td>
                                    <td class="v-align-middle">{{ $subscriber->age }}</td>
                                    <td class="v-align-middle">
                                        @if($subscriber->gender == 1)
                                            Male
                                        @elseif($subscriber->gender == 2)
                                            Female
                                        @endif
                                    </td>
                                    <td class="v-align-middle">{{ $subscriber->email }}</td>
                                    <td class="v-align-middle">{{ $subscriber->phone }}</td>
                                    <td class="v-align-middle">{{ $subscriber->nationality }}</td>
                                    <td class="v-align-middle">N{{ $subscriber->japanese_level }}</td>
                                    <td class="v-align-middle">{{ $subscriber->user_selected_lang }}</td>
                                    <td class="v-align-middle">{{ $subscriber->job_categories }}</td>
                                    <td class="v-align-middle">{{ $subscriber->areas }}</td>
                                    <td class="v-align-middle">
                                        {{ \Carbon\Carbon::parse($subscriber->created_at)->format('d-m-Y') }}
                                    </td>
                                    <td class="v-align-middle text-center">
                                        <a href="{{ route('admin.subscribers.detail', $subscriber->id) }}" class="btn btn-info btn-xs tip" title="Detail">
                                            <span class="fa fa-info-circle"></span>
                                        </a>

                                        <a href="{{ route('admin.subscribers.change-password', $subscriber->id) }}" class="btn btn-primary btn-xs tip" title="Click to change password of this user">
                                            <span class="fa fa-key"></span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach

                            @if($pagination)
                                <tr>
                                    <td class="text-center" colspan="12">{!! $pagination !!}</td>
                                </tr>
                            @endif
                        @else
                            <tr>
                                <td colspan="12">
                                    <div class="alert alert-danger text-center">No subscriber found</div>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
@endsection
