@extends('layouts.admin')

@section('title', 'Images')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">Images</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-upload"></i> Upload Image
                </div>
                <div class="panel-body">
                    <form id="upload-form" enctype="multipart/form-data">
                        @csrf
                        <div class="form-group">
                            <input type="file" name="file" id="image-file" accept="image/*">
                        </div>
                        <button type="submit" class="btn btn-info btn-sm">Upload</button>
                        <span id="upload-status"></span>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <i class="fa fa-images"></i> Image Gallery

                    <div class="pull-right">
                        <input type="text" id="image-search" class="form-control input-sm" placeholder="Search..." style="width: 200px; display: inline-block;">
                        <button id="refresh-images" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i></button>
                    </div>
                </div>
                <div class="panel-body">
                    <div id="image-gallery" class="row"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Image Modal -->
<div class="modal fade" id="edit-image-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Edit Image Info</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-image-id">
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" id="edit-image-title" class="form-control input-sm">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="edit-image-description" class="form-control input-sm" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info" id="save-image-info">Save</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-scripts')
<script>
$(function() {
    var baseUrl = $('#base_url').val();

    function loadImages(search) {
        $.post(baseUrl + '/admin/images/list', { search: search || '' }, function(response) {
            var html = '';
            $.each(response.data, function(i, img) {
                html += '<div class="col-md-2 col-sm-3 col-xs-4" style="margin-bottom: 15px; text-align: center;">';
                html += '<img src="' + img.thumb_url + '" class="img-thumbnail" style="max-width: 100%; cursor: pointer;" data-id="' + img.id + '" data-title="' + (img.title || '') + '" data-description="' + (img.description || '') + '">';
                html += '<small>' + img.file_name + '</small>';
                html += '</div>';
            });
            $('#image-gallery').html(html || '<p class="text-center">No images found</p>');
        });
    }

    loadImages();

    $('#refresh-images').click(function() { loadImages($('#image-search').val()); });
    $('#image-search').on('keyup', function() { loadImages($(this).val()); });

    $(document).on('click', '#image-gallery img', function() {
        $('#edit-image-id').val($(this).data('id'));
        $('#edit-image-title').val($(this).data('title'));
        $('#edit-image-description').val($(this).data('description'));
        $('#edit-image-modal').modal('show');
    });

    $('#save-image-info').click(function() {
        var id = $('#edit-image-id').val();
        $.post(baseUrl + '/admin/images/' + id + '/update-info', {
            title: $('#edit-image-title').val(),
            description: $('#edit-image-description').val()
        }, function(response) {
            if (response.success) {
                $('#edit-image-modal').modal('hide');
                loadImages($('#image-search').val());
            }
        });
    });

    $('#upload-form').on('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        $.ajax({
            url: baseUrl + '/admin/images/upload',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $('#upload-status').html('<span class="text-success">Uploaded!</span>');
                    $('#image-file').val('');
                    loadImages();
                }
            },
            error: function() {
                $('#upload-status').html('<span class="text-danger">Upload failed</span>');
            }
        });
    });
});
</script>
@endpush
