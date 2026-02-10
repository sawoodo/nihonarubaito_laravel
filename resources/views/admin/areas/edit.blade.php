@extends('layouts.admin')

@section('title', 'Edit Area')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Edit Area</h3>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success text-center">{{ session('success') }}</div>
    @endif

    @if($area)
        <form method="POST" action="{{ route('admin.areas.edit', $area->id) }}">
            @csrf

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Prefecture :</label>
                        <p class="form-control-static">{{ $prefecture_name }} (ID: {{ $area->town->prefecture_id ?? 'N/A' }})</p>
                    </div>
                </div>
            </div>

            @foreach(['chinese', 'english', 'japanese', 'korean', 'vietnamese'] as $lang)
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group {{ $errors->has($lang) ? 'has-error' : '' }}">
                            <label for="{{ $lang }}">Area In {{ ucfirst($lang) }} :</label>
                            <input type="text" id="{{ $lang }}" name="{{ $lang }}" class="form-control input-sm" value="{{ old($lang, $area->$lang) }}" placeholder="Enter area in {{ $lang }}">
                            @if($errors->has($lang))
                                <span class="help-block">{{ $errors->first($lang) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="submit" class="btn btn-info">Update</button>
        </form>
    @else
        <div class="alert alert-danger text-center">Area not found</div>
    @endif

</div>
@endsection
