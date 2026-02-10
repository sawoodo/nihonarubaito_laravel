@extends('layouts.admin')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Images</h3>
        </div>
    </div>

    <div class="row">
        <div id="image_msg_container" class="col-md-12"></div>
    </div>

    <!-- Image Upload -->
    <div class="row">
        <!-- Saved Images Panel -->
        <div class="col-md-9">

            <div class="panel panel-grey">

                <div class="panel-body">
                    <div id="saved_images_container">

                        <div class="row">

                            @if ($images->count())
                                @foreach ($images as $i => $image)
                                    @php $img_src = $image->name . $image->ext; @endphp

                                    <div class="col-md-4 image">
                                        <div class="thumbnail">
                                            <div class="actions position-right">
                                                <button
                                                    class="btn btn-xs btn-warning tip btn-edit tip"
                                                    title="Edit Caption"
                                                    data-image-path="{{ url('frontend/images/jobs/' . $img_src) }}"
                                                    data-image-id="{{ $image->id }}"
                                                    data-image-title="{{ $image->title }}"
                                                    data-image-description="{{ $image->description }}">
                                                    <span class="glyphicon glyphicon-pencil"></span>
                                                </button>
                                            </div>

                                            <img class="img-responsive" src="{{ url('frontend/images/jobs/' . $img_src) }}" alt="{{ $image->name }}">
                                            <div class="caption text-center">
                                                <h4>{{ $image->title != '' ? $image->title : 'No Title' }}</h4>
                                                <p>{{ $image->description != '' ? $image->description : 'No Description' }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    @if (($i + 1) % 3 == 0)
                                        </div><div class="row">
                                    @endif
                                @endforeach
                            @else
                                <div class="col-md-12 no-file">
                                    <div class="alert alert-danger text-center">No image found</div>
                                </div>
                            @endif
                        </div>

                    </div>
                </div> <!-- /panel-body -->

            </div>

        </div>

        <div class="col-md-3">

            <form action="{{ url('admin/images/upload') }}" id="myDropzone" class="dropzone" method="post" enctype="multipart/form-data">
                @csrf
            </form>

        </div>

    </div>

</div>


<div id="edit-image-modal" class="modal fade" tabindex="-1" role="dialog" data-backdrop="static" data-keyboard="false">
    <div class="modal-dialog modal-md" role="document">
        <div class="modal-content">

            <div class="modal-header">

                <div class="btn-toolbar pull-right" role="toolbar">
                    <button class="btn btn-default btn-xs tip" data-dismiss="modal" aria-label="Close" role="group" title="Close">
                        <i class="fa fa-times"></i>
                    </button>
                </div>

                <h4 class="modal-title">Edit Image Information</h4>
            </div>

            <div class="modal-body">
                <div class="row">
                    <div class="col-md-12">
                        <img src="#" class="img-responsive">

                        <br>
                        <div class="form-group">
                            <label for="title">Title</label>
                            <input type="text" name="title" id="title" class="form-control" placeholder="Enter Title">
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <input type="text" name="description" id="description" class="form-control" placeholder="Enter Description">
                        </div>

                        <input type="hidden" id="hidden_image_id" value="0">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-default" data-dismiss="modal">Close</button>
                <button id="btn-update" class="btn btn-success">Update</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-styles')
    <link href="{{ url('plugins/dropzone/basic.min.css') }}" rel="stylesheet" type="text/css">
    <link href="{{ url('plugins/dropzone/dropzone.css') }}" rel="stylesheet" type="text/css">
@endpush

@push('page-scripts')
    <script src="{{ url('plugins/dropzone/dropzone.min.js') }}"></script>
    <script src="{{ mix('/js/backend-app-pages/images/upload_image_dz_options.js') }}"></script>
    <script src="{{ mix('/js/backend-app-pages/images/upload_image.js') }}"></script>
@endpush
