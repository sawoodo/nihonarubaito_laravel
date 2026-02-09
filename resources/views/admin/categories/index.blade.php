@extends('layouts.admin')

@section('title', 'Categories')

@section('content')
<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <h3 class="page-header">
                Categories

                <a href="{{ route('admin.categories.create') }}" class="btn btn-success btn-sm pull-right">
                    Create
                </a>
            </h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">

            @if(session('success'))
                <div class="alert alert-success text-center">
                    {{ session('success') }}
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Chinese</th>
                            <th>English</th>
                            <th>Japanese</th>
                            <th>Korean</th>
                            <th>Vietnamese</th>
                            <th>Edit</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td>{{ $category->chinese }}</td>
                                <td>{{ $category->english }}</td>
                                <td>{{ $category->japanese }}</td>
                                <td>{{ $category->korean }}</td>
                                <td>{{ $category->vietnamese }}</td>
                                <td>
                                    <a href="{{ route('admin.categories.edit', $category->id) }}" class="btn btn-default btn-xs" title="Edit">
                                        <span class="glyphicon glyphicon-edit"></span>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="alert alert-danger text-center">No categories found</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>
@endsection
