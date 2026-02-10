@extends('layouts.admin')

@section('title', 'Edit Work')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Edit Work</h3>
        </div>
    </div>

    @if($work)
        <form method="POST" action="{{ route('admin.works.edit', $work->id) }}">
            @csrf

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group {{ $errors->has('chinese') ? 'has-error' : '' }}">
                        <label for="chinese">Work In Chinese :</label>
                        <input type="text" id="chinese" name="chinese" class="form-control input-sm" value="{{ old('chinese', $work->chinese) }}" placeholder="Enter work in chinese">
                        @error('chinese') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group {{ $errors->has('english') ? 'has-error' : '' }}">
                        <label for="english">Work In English :</label>
                        <input type="text" id="english" name="english" class="form-control input-sm" value="{{ old('english', $work->english) }}" placeholder="Enter work in english">
                        @error('english') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group {{ $errors->has('japanese') ? 'has-error' : '' }}">
                        <label for="japanese">Work In Japanese :</label>
                        <input type="text" id="japanese" name="japanese" class="form-control input-sm" value="{{ old('japanese', $work->japanese) }}" placeholder="Enter work in japanese">
                        @error('japanese') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="form-group {{ $errors->has('korean') ? 'has-error' : '' }}">
                        <label for="korean">Work In Korean :</label>
                        <input type="text" id="korean" name="korean" class="form-control input-sm" value="{{ old('korean', $work->korean) }}" placeholder="Enter work in korean">
                        @error('korean') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group {{ $errors->has('vietnamese') ? 'has-error' : '' }}">
                        <label for="vietnamese">Work In Vietnamese :</label>
                        <input type="text" id="vietnamese" name="vietnamese" class="form-control input-sm" value="{{ old('vietnamese', $work->vietnamese) }}" placeholder="Enter work in vietnamese">
                        @error('vietnamese') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-info">Update</button>
        </form>
    @else
        <div class="alert alert-danger text-center">Work not found</div>
    @endif

</div>
@endsection
