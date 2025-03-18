@extends('layouts.main')
@section('title')
    @if($category_data->type == 'providers')
        {{__("Edit Provider Category")}}
    @else
        {{__("Edit Service & Experience Category")}}
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
            @if($category_data->type == 'providers')
                <a class="btn btn-primary" href="{{ route('category.index', ['type' => 'providers']) }}">< {{__("Back to Provider Categories")}} </a>
            @else
                <a class="btn btn-primary" href="{{ route('category.index', ['type' => 'service_experience']) }}">< {{__("Back to Service & Experience Categories")}} </a>
            @endif
        </div>
        <div class="row">
            <form action="{{ route('category.update', $category_data->id) }}" method="POST" data-parsley-validate enctype="multipart/form-data">
                @method('PUT')
                @csrf
                <input type="hidden" name="edit_data" value={{ $category_data->id }}>
                <input type="hidden" name="type" value="{{ $category_data->type }}">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            @if($category_data->type == 'providers')
                                {{__("Edit Provider Category")}}
                            @else
                                {{__("Edit Service & Experience Category")}}
                            @endif
                        </div>
                        <div class="card-body mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="name" class="mandatory form-label">{{ __('Name') }}</label>
                                        <input type="text" name="name" id="name" class="form-control" data-parsley-required="true" value="{{ $category_data->name }}">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group">
                                        <label for="p_category" class="form-label">{{ __('Parent Category') }}</label>
                                        <select name="parent_category_id" id="p_category" class="form-select form-control" data-placeholder="{{__("Select Category")}}">
                                            <option value="">{{__("Select a Category")}}</option>
                                            @if(!empty($parentCategories))
                                                @foreach($parentCategories as $category)
                                                    <option value="{{ $category['id'] }}" {{ $category_data->parent_category_id == $category['id'] ? 'selected' : '' }}>
                                                        {{ str_repeat('- ', $category['level']) . $category['name'] }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="slug" class="form-label">{{ __('Slug') }} <small>(English Only)</small></label>
                                        <input type="text" name="slug" id="slug" class="form-control" data-parsley-required="true" value="{{ $category_data->slug }}">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="description" class="mandatory form-label">{{ __('Description') }}</label>
                                    <textarea name="description" id="description" class="form-control" cols="10" rows="5">{{ $category_data->description }}</textarea>
                                    <div class="form-check form-switch mt-3">
                                        <input type="hidden" name="status" id="status" value="{{ $category_data->status}}">
                                        <input class="form-check-input status-switch" type="checkbox" role="switch" aria-label="status" name="active" id="required" {{ $category_data->status == 1 ? 'checked' : '' }}>{{ __('Active') }}
                                        <label class="form-check-label" for="status"></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="col-md-12 form-group mandatory">
                                        <label for="Field Name" class="mandatory form-label">{{ __('Image') }}</label>
                                        <div class="cs_field_img">
                                            <input type="file" name="image" class="image" style="display: none" accept=" .jpg, .jpeg, .png, .svg">
                                            <img src="{{ empty($category_data->image) ? asset('assets/img_placeholder.jpeg') : $category_data->image }}" alt="" class="img preview-image" id="">
                                            <div class='img_input'>{{__("Browse File")}}</div>
                                        </div>
                                        <div class="input_hint"> {{__("Icon (use 256 x 256 size for better view)")}}</div>
                                        <div class="img_error" style="color:#DC3545;"></div>
                                    </div>
                                </div>

                            </div>
                            @if($languages->isNotEmpty())
                            <hr>
                            <h5>{{ __("Translation") }}</h5>
                            <div class="row">
                                @foreach($languages as $key => $language)
                                    <div class="col-md-6 form-group">
                                        <label for="name_{{$language->id}}" class="form-label">
                                            {{ ($key + 1) . ". " . $language->name }}:
                                        </label>
                                        <input name="translations[{{$language->id}}]" id="name_{{$language->id}}" class="form-control" value="{{ $translations[$language->id] ?? '' }}">
                                    </div>
                                @endforeach
                            </div>
                        @endif

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

<!-- Make sure jQuery is loaded -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize image upload handling
        const imgInput = document.querySelector('.img_input');
        const fileInput = document.querySelector('input[type="file"].image');
        const previewImage = document.querySelector('.preview-image');
        
        if (imgInput && fileInput && previewImage) {
            imgInput.addEventListener('click', function() {
                fileInput.click();
            });
            
            fileInput.addEventListener('change', function() {
                if (fileInput.files && fileInput.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                    };
                    reader.readAsDataURL(fileInput.files[0]);
                }
            });
        }
        
        // Handle status toggle
        const statusCheckbox = document.querySelector('.status-switch');
        const statusInput = document.getElementById('status');
        
        if (statusCheckbox && statusInput) {
            statusCheckbox.addEventListener('change', function() {
                statusInput.value = this.checked ? '1' : '0';
            });
        }
    });
</script>
