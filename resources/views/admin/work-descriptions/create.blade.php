@extends('layouts.admin')

@section('title', 'Create Work Description')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Create Work Description</h3>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.work-descriptions.create') }}">
        @csrf

        <div class="row">
            <div class="col-md-3">
                <div class="form-group {{ $errors->has('work_id') ? 'has-error' : '' }}">
                    <label for="work_id">Work :</label>
                    <select name="work_id" id="work_id" class="form-control input-sm">
                        <option value="">Please select</option>
                        @foreach($works as $id => $name)
                            <option value="{{ $id }}" {{ (int) old('work_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('work_id') <span class="help-block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        @foreach(['chinese', 'english', 'japanese', 'korean', 'vietnamese'] as $lang)
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has($lang) ? 'has-error' : '' }}">
                        <label for="{{ $lang }}">Description In {{ ucfirst($lang) }} :</label>
                        <input type="text" id="{{ $lang }}" name="{{ $lang }}" class="form-control input-sm" value="{{ old($lang) }}" placeholder="Enter description in {{ $lang }}">
                        @error($lang) <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-info">Create</button>
    </form>

</div>
@endsection
