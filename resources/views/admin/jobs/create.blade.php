@extends('layouts.admin')

@section('content')
    <div class="container-fluid">

        <div class="row">
            <div class="col-md-12">
                <h3 class="page-header">Create New Job</h3>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3">
                <div id="image-box" class="controls">
                    @if ($images_img_id > 0)
                        <div id="uploaded-image" class="thumbnail">
                            <button id="btn-remove-image" class="btn btn-xs btn-danger del-btn-pos-right tip" title="Click to remove the image.">
                                <i class="fa fa-times"></i>
                            </button>
                            <button id="btn-change-image" class="btn btn-xs btn-primary del-btn-pos-right margin-right-30px tip" title="Click to change the image.">
                                <i class="fa fa-pencil"></i>
                            </button>
                            <img class="img-responsive" src="{{ url("frontend/images/jobs/{$images_img_name}{$images_img_ext}") }}" alt="{{ $images_img_name }}">
                        </div>
                        <div class="well no-image clickable min-height-150" style="display: none;">
                            <div class="upload-control text-center">
                                <h3><i class="fa fa-picture-o"></i></h3>
                                <h4>Click to select image</h4>
                            </div>
                        </div>
                    @else
                        <div class="well no-image clickable min-height-150">
                            <div class="upload-control text-center">
                                <h3><i class="fa fa-picture-o"></i></h3>
                                <h4>Click to select image</h4>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <form action="{{ url('admin/jobs/create') }}" method="POST" class="tw-mb-5">
            @csrf

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has('img_link') ? 'has-error' : '' }}">
                        <label for="img_link">Image Link :</label>
                        <input type="text" id="img_link" name="img_link" class="form-control input-sm" value="{{ old('img_link', '') }}" placeholder="Enter image link">
                        @error('img_link') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>

                @if (isset($language_list))
                    <div class="col-md-3">
                        <div class="form-group {{ $errors->has('lang_id') ? 'has-error' : '' }}">
                            <label for="lang_id">Language :</label>
                            <select name="lang_id" id="lang_id" class="form-control input-sm">
                                @foreach ($language_list as $id => $name)
                                    <option value="{{ $id }}" {{ (int) old('lang_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('lang_id') <span class="help-block">{{ $message }}</span> @enderror
                        </div>
                    </div>
                @endif

                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('delete_at') ? 'has-error' : '' }}">
                        <label for="delete_at">Days to Delete :</label>
                        <input type="text" id="delete_at" name="delete_at" class="form-control input-sm number" value="{{ old('delete_at', '60') }}" placeholder="Enter days after the job will be deleted">
                        @error('delete_at') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has('title') ? 'has-error' : '' }}">
                        <label for="title">Title :</label>
                        <input type="text" id="title" name="title" class="form-control input-sm" value="{{ old('title', '') }}" placeholder="Enter title">
                        @error('title') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has('company_name') ? 'has-error' : '' }}">
                        <label for="company_name">Company Name :</label>
                        <input type="text" id="company_name" name="company_name" class="form-control input-sm" value="{{ old('company_name', '') }}" placeholder="Enter company name">
                        @error('company_name') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="form-group {{ $errors->has('description') ? 'has-error' : '' }}">
                        <label for="description">Description :</label>
                        <input type="text" id="description" name="description" class="form-control input-sm" value="{{ old('description', '') }}" placeholder="Enter description">
                        @error('description') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('job_category_id') ? 'has-error' : '' }}">
                        <label for="job_category_id">Job Category :</label>
                        <select name="job_category_id" id="job_category_id" class="form-control input-sm">
                            @foreach ($job_cat_list as $id => $name)
                                <option value="{{ $id }}" {{ (int) old('job_category_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('job_category_id') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('prefecture_id') ? 'has-error' : '' }}">
                        <label for="prefecture_id">Prefecture :</label>
                        <select name="prefecture_id" id="prefecture_id" class="form-control input-sm select2">
                            @foreach ($prefecture_list as $id => $name)
                                <option value="{{ $id }}" {{ (int) old('prefecture_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('prefecture_id') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('area_id') ? 'has-error' : '' }}">
                        <label for="area_id">Area :</label>
                        <select name="area_id" id="area_id" class="form-control input-sm select2">
                            @foreach ($area_list as $id => $name)
                                <option value="{{ $id }}" {{ (int) old('area_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('area_id') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has('station') ? 'has-error' : '' }}">
                        <label for="station">Station :</label>
                        <textarea id="station" name="station" class="form-control input-sm" placeholder="Enter station" style="resize: none;">{{ old('station', '') }}</textarea>
                        @error('station') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has('address') ? 'has-error' : '' }}">
                        <label for="address">Address :</label>
                        <input type="text" id="address" name="address" class="form-control input-sm" value="{{ old('address', '') }}" placeholder="Enter address">
                        @error('address') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group {{ $errors->has('japanese_level') ? 'has-error' : '' }}">
                        <label for="japanese_level">Japanese Level :</label>
                        <select name="japanese_level" id="japanese_level" class="form-control input-sm">
                            @foreach ($jap_level_list as $id => $name)
                                <option value="{{ $id }}" {{ (int) old('japanese_level') === $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('japanese_level') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('working_hours') ? 'has-error' : '' }}">
                        <label for="working_hours">Working Hours :</label>
                        <input type="text" id="working_hours" name="working_hours" class="form-control input-sm" value="{{ old('working_hours', '') }}" placeholder="Enter working hours">
                        @error('working_hours') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('working_days') ? 'has-error' : '' }}">
                        <label for="working_days">Working Days :</label>
                        <input type="text" id="working_days" name="working_days" class="form-control input-sm" value="{{ old('working_days', '') }}" placeholder="Enter working days">
                        @error('working_days') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('wage') ? 'has-error' : '' }}">
                        <label for="wage">Wage :</label>
                        <input type="text" id="wage" name="wage" class="form-control input-sm" value="{{ old('wage', '') }}" placeholder="Enter wage">
                        @error('wage') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('wage_type_id') ? 'has-error' : '' }}">
                        <label>Wage Type :</label><br>
                        @foreach ($wage_type_list as $id => $wageType)
                            @if ($id != 0)
                                <label class="radio-inline">
                                    <input type="radio" name="wage_type_id" id="wage_type_id_{{ $id }}" value="{{ $id }}" {{ (int) old('wage_type_id') === $id ? 'checked' : '' }}>
                                    {{ $wageType }}
                                </label>
                            @endif
                        @endforeach
                        @error('wage_type_id') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('trans_exp_id') ? 'has-error' : '' }}">
                        <label for="trans_exp_id">Transportation Expense :</label>
                        <select name="trans_exp_id" id="trans_exp_id" class="form-control input-sm">
                            @foreach ($trans_exp_list as $id => $name)
                                <option value="{{ $id }}" {{ (int) old('trans_exp_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('trans_exp_id') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="form-group {{ $errors->has('requirement') ? 'has-error' : '' }}">
                        <label for="requirement">Requirement :</label>
                        <input type="text" id="requirement" name="requirement" class="form-control input-sm" value="{{ old('requirement', '') }}" placeholder="Enter requirement">
                        @error('requirement') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-5">
                    <div class="form-group {{ $errors->has('apply_link') ? 'has-error' : '' }}">
                        <label for="apply_link">Apply Link :</label>
                        <textarea id="apply_link" name="apply_link" class="form-control input-sm" placeholder="Enter apply link" rows="3" style="resize: none;">{{ old('apply_link', '') }}</textarea>
                        @error('apply_link') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group {{ $errors->has('user_id') ? 'has-error' : '' }}">
                        <label for="user_id">Advertiser :</label>
                        <select name="user_id" id="user_id" class="form-control input-sm">
                            @foreach ($advertiser_list as $id => $name)
                                <option value="{{ $id }}" {{ (int) old('user_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('user_id') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <label>Featured :</label>
                        <div class="text-center">
                            <div class="animated-checkbox">
                                <input type="checkbox" name="featured" value="1" {{ old('featured', $featured) ? 'checked' : '' }}>
                                <svg viewBox="0 0 35.6 35.6">
                                    <circle class="background" cx="17.8" cy="17.8" r="17.8"></circle>
                                    <circle class="stroke" cx="17.8" cy="17.8" r="14.37"></circle>
                                    <polyline class="check" points="11.78 18.12 15.55 22.23 25.17 12.87"></polyline>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="text-center">
                        <label>Send Email :</label>
                        <div class="text-center">
                            <div class="animated-checkbox">
                                <input type="checkbox" name="send_email" value="1" {{ old('send_email', $send_email) ? 'checked' : '' }}>
                                <svg viewBox="0 0 35.6 35.6">
                                    <circle class="background" cx="17.8" cy="17.8" r="17.8"></circle>
                                    <circle class="stroke" cx="17.8" cy="17.8" r="14.37"></circle>
                                    <polyline class="check" points="11.78 18.12 15.55 22.23 25.17 12.87"></polyline>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="text-right">
                        <button type="submit" class="btn tw-btn-sky">Create</button>
                    </div>
                </div>
            </div>

            <input type="hidden" id="job_id" name="job_id" value="0">
            <input type="hidden" id="job_no" name="job_no" value="">
            <input type="hidden" id="hid_area_id" name="hid_area_id" value="{{ old('area_id', 0) }}">
            <input type="hidden" id="hid_images_img_id" name="images_img_id" value="{{ $images_img_id }}">
        </form>

    </div>

    @include('admin.jobs.partials.image-gallery-modal')
@endsection

@push('page-styles')
    <link href="{{ url('plugins/select2/css/select2.min.css') }}" rel="stylesheet">
@endpush

@push('page-scripts')
    <script src="{{ url('plugins/slimScroll/jquery.slimscroll.min.js') }}"></script>
    <script src="{{ url('plugins/selectable/jquery.selectable.min.js') }}"></script>
    <script src="{{ url('plugins/select2/js/select2.min.js') }}"></script>
    <script src="{{ mix('/js/backend-app-pages/admin/jobs/create_edit.js') }}"></script>
@endpush
