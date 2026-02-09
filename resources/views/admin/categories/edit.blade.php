@extends('layouts.admin')

@section('title', 'Edit Category')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Edit Category</h3>
        </div>
    </div>

    @if($category)
        <form method="POST" action="{{ route('admin.categories.edit', $category->id) }}">
            @csrf

            @foreach(['chinese', 'english', 'japanese', 'korean', 'vietnamese'] as $lang)
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group {{ $errors->has($lang) ? 'has-error' : '' }}">
                            <label for="{{ $lang }}">{{ ucfirst($lang) }} :</label>
                            <input type="text" id="{{ $lang }}" name="{{ $lang }}" class="form-control input-sm" value="{{ old($lang, $category->$lang) }}" placeholder="Enter category in {{ $lang }}">
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
        <div class="alert alert-danger text-center">Category not found</div>
    @endif

</div>
@endsection
