@extends('layouts.admin')

@section('title', 'Create Category')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Create Category</h3>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.categories.create') }}">
        @csrf

        @foreach(['chinese', 'english', 'japanese', 'korean', 'vietnamese'] as $lang)
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has($lang) ? 'has-error' : '' }}">
                        <label for="{{ $lang }}">{{ ucfirst($lang) }} :</label>
                        <input type="text" id="{{ $lang }}" name="{{ $lang }}" class="form-control input-sm" value="{{ old($lang) }}" placeholder="Enter category in {{ $lang }}">
                        @if($errors->has($lang))
                            <span class="help-block">{{ $errors->first($lang) }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-info">Create</button>
    </form>

</div>
@endsection
