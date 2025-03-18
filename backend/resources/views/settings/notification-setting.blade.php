@extends('layouts.main')

@section('title')
    {{ __('Notification Settings') }}
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first"></div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <form class="create-form-without-reset" action="{{route('settings.store') }}" method="post" enctype="multipart/form-data" data-success-function="successFunction" data-parsley-validate>
            @csrf
            <div class="row d-flex mb-3">
                {{-- <div class="col-md-4"> --}}
                    <div class="card mb-0">
                        <div class="card-body">
                            <div class="divider pt-3">
                                <h6 class="divider-text">{{ __('FCM Notification Settings') }}</h6>
                            </div>
                            <div class="form-group row mt-3">
                                <div class="col-md-6 col-sm-12">
                                    <label for="firebase_project_id" class="form-label">{{ __('Firebase Project Id') }}</label>
                                    <input type="text" id="firebase_project_id" name="firebase_project_id" class="form-control" placeholder="{{ __('Firebase Project Id') }}" value="{{ $settings['firebase_project_id'] ?? '' }}"/>
                                </div>
                                <div class="col-md-6 col-sm-12">
                                    <label for="service_file" class="form-label">{{ __('Service Json File') }}</label><span style="color: #00B2CA">{{__('(Accept only Json File)')}}</span>
                                    <input id="service_file" name="service_file" type="file" class="form-control">
                                    <p style="display: none" id="img_error_msg" class="badge rounded-pill bg-danger"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                {{-- </div> --}}
            </div>
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" value="btnAdd" class="btn btn-primary me-1 mb-3">{{ __('Save') }}</button>
            </div>
        </form>
    </section>
@endsection
@section('js')
    <script>
        function successFunction() {
            window.location.reload();
        }
    </script>
@endsection
