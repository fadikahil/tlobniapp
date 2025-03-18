@extends('layouts.main')
@section('title')
    @if(isset($type) && $type == 'providers')
        {{__("Create Provider Category")}}
    @else
        {{__("Create Service & Experience Category")}}
    @endif
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
                <h4>@yield('title')</h4>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="buttons">
            @if(isset($type) && $type == 'providers')
                <a class="btn btn-primary" href="{{ route('category.providers') }}">< {{__("Back to Provider Categories")}} </a>
            @else
                <a class="btn btn-primary" href="{{ route('category.service.experience') }}">< {{__("Back to Service & Experience Categories")}} </a>
            @endif
        </div>
        <div class="row">
            <form action="{{ route('category.store') }}" method="POST" data-parsley-validate enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="type" value="{{ $type ?? 'service_experience' }}">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            @if(isset($type) && $type == 'providers')
                                {{__("Add Provider Category")}}
                            @else
                                {{__("Add Service & Experience Category")}}
                            @endif
                        </div>

                        <div class="card-body mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="category_name" class="mandatory form-label">{{ __('Name') }}</label>
                                        <input type="text" name="name" id="category_name" class="form-control" data-parsley-required="true">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="category_slug" class="form-label">{{ __('Slug') }} <small>{{__('(English Only)')}}</small></label>
                                        <input type="text" name="slug" id="category_slug" class="form-control" data-parsley-required="true">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="col-md-12 form-group">
                                        <label for="p_category" class="form-label">{{ __('Parent Category') }}</label>
                                        <select name="parent_category_id" id="p_category" class="form-select form-control" data-placeholder="{{__("Select Category")}}">
                                            <option value="">{{__("Select a Category")}}</option>
                                            @if(!empty($parentCategories))
                                                @foreach($parentCategories as $category)
                                                    <option value="{{ $category['id'] }}">{{ str_repeat('- ', $category['level']) . $category['name'] }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="col-md-12 form-group">
                                        <label for="Field Name" class="form-label">{{ __('Image') }}</label>
                                        <input type="file" name="image" id="image" class="form-control" accept=".jpg,.jpeg,.png">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="description" class="form-label">{{ __('Description') }}</label>
                                    <textarea name="description" id="description" class="form-control" cols="10" rows="5"></textarea>
                                    <div class="form-check form-switch mt-3">
                                        <input type="hidden" name="status" id="status" value="0">
                                        <input class="form-check-input status-switch" type="checkbox" role="switch" aria-label="status">{{ __('Active') }}
                                        <label class="form-check-label" for="status"></label>
                                    </div>
                                </div>


                                @if($languages->isNotEmpty())
                                <div class="row">
                                    <hr>
                                    <h5>{{ __("Translation") . " " . __("Optional") }}</h5>

                                    @foreach($languages as $key => $language)
                                        <div class="col-md-6 form-group">
                                            <label for="name_{{$language->id}}" class="form-label">{{ ($key + 1) . ". " . $language->name }}:</label>
                                            <input name="translations[{{$language->id}}]" id="name_{{$language->id}}" class="form-control" value="">
                                        </div>
                                    @endforeach
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 text-end">
                        <input type="submit" class="btn btn-primary" value="{{__("Save and Back")}}">
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    // No need to load parent categories on page load since they're pre-loaded from the server
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize any components if needed
        
        // Set the type value to the hidden input if changing type is needed in the future
        document.getElementById('category_slug').addEventListener('blur', function() {
            // Auto-generate slug if empty
            if (this.value.trim() === '') {
                const nameField = document.getElementById('category_name');
                if (nameField.value.trim() !== '') {
                    this.value = nameField.value.trim()
                        .toLowerCase()
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/-+/g, '-')
                        .replace(/^-|-$/g, '');
                }
            }
        });
    });
</script>
@endpush


