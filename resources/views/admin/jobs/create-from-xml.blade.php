@extends('layouts.admin')

@section('title', 'Create Job from XML')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header text-center">Drag &amp; Drop XML Job File to Upload</h3>
        </div>
    </div>

    @include('partials.admin.flash-message')

    <div class="row">
        <div class="col-md-12">
            <form action="{{ url('admin/jobs/create-from-xml/upload-file') }}" id="myDropzone" class="dropzone tw-bg-amber-300 tw-rounded-3xl" method="POST">
                @csrf
                <div class="dz-default dz-message text-center">
                    <h3><i class="fa fa-file-code-o fa-4x"></i></h3>
                    <h4>Click OR drag &amp; drop an XML file containing jobs.</h4>
                </div>
            </form>
        </div>
    </div>

    <h3 class="text-center">OR</h3>

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Create New Job Manually</h3>
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

    @if (session('duplicate_warning'))
        @php $dup = session('duplicate_warning'); @endphp
        <div class="alert alert-warning" style="border-left: 4px solid {{ $dup['level'] === 'high' ? '#e74c3c' : ($dup['level'] === 'medium' ? '#f39c12' : '#f1c40f') }};">
            <strong>Possible Duplicate Found ({{ $dup['label'] }} confidence):</strong><br>
            Job <a href="{{ url('admin/jobs/' . $dup['job_no'] . '/view') }}" target="_blank"><strong>#{{ $dup['job_no'] }}</strong></a>
            — {{ \Illuminate\Support\Str::limit($dup['title'], 80) }}
            — Status: {{ $dup['status'] }}
            — Created: {{ $dup['date'] }}<br>
            <small>You can still create this job by clicking "Create Anyway" below.</small>
        </div>
    @endif

    <form action="{{ url('admin/jobs/create-from-xml') }}" method="POST" class="tw-mb-5">
        @csrf
        @if (session('duplicate_warning'))
            <input type="hidden" name="skip_duplicate_check" value="1">
            <div class="text-right tw-mb-3">
                <button type="submit" class="btn btn-warning"><i class="fa fa-exclamation-triangle"></i> Create Anyway (Ignore Duplicate)</button>
            </div>
        @endif

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
                                <option value="{{ $id }}" {{ (int) old('lang_id', 1) === $id ? 'selected' : '' }}>{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('lang_id') <span class="help-block">{{ $message }}</span> @enderror
                    </div>
                </div>
            @endif

            <div class="col-md-3">
                <div class="form-group {{ $errors->has('delete_at') ? 'has-error' : '' }}">
                    <label for="delete_at">Days to Delete :</label>
                    <input type="text" id="delete_at" name="delete_at" class="form-control input-sm number" value="{{ old('delete_at', '10') }}" placeholder="Enter days after the job will be deleted">
                    @error('delete_at') <span class="help-block">{{ $message }}</span> @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="form-group {{ $errors->has('xml_data') ? 'has-error' : '' }}">
                    <label for="xml_data">XML Data :</label>
                    <textarea id="xml_data" name="xml_data" class="form-control input-sm" placeholder="Paste XML data here" style="resize: none;" rows="10">{{ old('xml_data', '') }}</textarea>
                    @error('xml_data') <span class="help-block">{{ $message }}</span> @enderror
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
                <div class="form-group">
                    <label for="user_id">Advertiser :</label>
                    <select name="user_id" id="user_id" class="form-control input-sm">
                        @foreach ($advertiser_list as $id => $name)
                            <option value="{{ $id }}" {{ (int) old('user_id') === $id ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
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
        <input type="hidden" id="hid_images_img_id" name="images_img_id" value="{{ $images_img_id }}">
    </form>

</div>

@include('admin.jobs.partials.image-gallery-modal')
@endsection

@push('page-styles')
    <link href="{{ url('plugins/dropzone/basic.min.css') }}" rel="stylesheet">
    <link href="{{ url('plugins/dropzone/dropzone.css') }}" rel="stylesheet">
@endpush

@push('page-scripts')
    <script src="{{ url('plugins/slimScroll/jquery.slimscroll.min.js') }}"></script>
    <script src="{{ url('plugins/selectable/jquery.selectable.min.js') }}"></script>
    <script src="{{ url('plugins/dropzone/dropzone.min.js') }}"></script>
    <script src="{{ mix('/js/backend-app-pages/admin/jobs/create_from_xml.js') }}"></script>
@endpush
