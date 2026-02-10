@extends('layouts.admin')

@section('title', 'Edit Work Description')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Edit Work Description</h3>
        </div>
    </div>

    @if($description)
        <form method="POST" action="{{ route('admin.work-descriptions.edit', $description->id) }}">
            @csrf

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label>Work :</label>
                        <p class="form-control-static">{{ $description->work->english ?? 'N/A' }}</p>
                        <input type="hidden" name="work_id" value="{{ $description->work_id }}">
                    </div>
                </div>
            </div>

            @foreach(['chinese', 'english', 'japanese', 'korean', 'vietnamese'] as $lang)
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group {{ $errors->has($lang) ? 'has-error' : '' }}">
                            <label for="{{ $lang }}">Description In {{ ucfirst($lang) }} :</label>
                            <input type="text" id="{{ $lang }}" name="{{ $lang }}" class="form-control input-sm" value="{{ old($lang, $description->$lang) }}" placeholder="Enter description in {{ $lang }}">
                            @error($lang) <span class="help-block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="submit" class="btn btn-info">Update</button>
        </form>
    @else
        <div class="alert alert-danger text-center">Work description not found</div>
    @endif

</div>
@endsection
