@extends('layouts.main')
@section('title')
    @if(isset($type) && $type == 'providers')
        {{__("Provider Categories")}}
    @else
        {{__("Service & Experience Categories")}}
    @endif
@endsection

@section('page-title')
    <div class="page-title">
        <div class="row align-items-center">
            <div class="col-12 col-md-6">
                <h4 class="mb-0">@yield('title')</h4>
            </div>
            <div class="col-12 col-md-6 d-flex justify-content-end">
                @if (!empty($category))
                    @if(isset($type) && $type == 'providers')
                        <a class="btn btn-primary me-2" href="{{ route('category.index', ['type' => 'providers']) }}">< {{__("Back to All Provider Categories")}} </a>
                    @else
                        <a class="btn btn-primary me-2" href="{{ route('category.index', ['type' => 'service_experience']) }}">< {{__("Back to All Service & Experience Categories")}} </a>
                    @endif
                    @can('category-create')
                        <a class="btn btn-primary me-2" href="{{ route('category.create', ['id' => $category->id, 'type' => $type ?? 'service_experience']) }}">+ {{__("Add Subcategory")}} - /{{ $category->name }} </a>
                    @endcanany
                @else
                    @can('category-create')
                        <a class="btn btn-primary" href="{{ route('category.create', ['type' => $type ?? 'service_experience']) }}">+ {{__("Add Category")}} </a>
                    @endcan
                @endif
            </div>
        </div>
    </div>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-md-12">
                <div class="card">

                    <div class="card-body">
                        {{-- <div class="row">
                            <div class="text-right col-md-12">
                                <a href="{{ route('category.order') }}">+ {{__("Set Order of Categories")}} </a>
                            </div>
                        </div> --}}
                        
                        @if(!isset($type) || $type == 'service_experience')
                            <table class="table table-borderless table-striped" aria-describedby="mydesc"
                               id="table_list_service" data-toggle="table" data-url="{{ route('category.show', ['category' => 0, 'type' => 'service_experience']) }}"
                               data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                               data-page-list="[5, 10, 20, 50, 100, 200,500,2000]" data-search="true" data-search-align="right"
                               data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                               data-trim-on-search="false" data-responsive="true" data-sort-name="sequence"
                               data-sort-order="asc" data-pagination-successively-size="3" data-query-params="queryParamsService"
                               data-escape="true"
                               data-table="categories" data-use-row-attr-func="true" data-mobile-responsive="false"
                               data-show-export="true" data-export-options='{"fileName": "category-list-service","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']">
                                <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-align="center" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true" data-formatter="categoryNameFormatter">{{ __('Name') }}</th>
                                    <th scope="col" data-field="image" data-align="center" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                    <th scope="col" data-field="subcategories_count" data-align="center" data-sortable="true" data-formatter="subCategoryFormatter">{{ __('Subcategories') }}</th>
                                    <th scope="col" data-field="custom_fields_count" data-align="center" data-sortable="true" data-formatter="customFieldFormatter">{{ __('Custom Fields') }}</th>
                                    <th scope="col" data-field="items_count" data-sortable="true" data-align="center" data-formatter="">{{ __('Item Count') }}</th>
                                    @can('category-update')
                                        <th scope="col" data-field="status" data-width="5" data-sortable="true"  data-formatter="statusSwitchFormatter">{{ __('Active') }}</th>
                                    @endcan
                                    @canany(['category-update', 'category-delete'])
                                        <th scope="col" data-field="operate" data-escape="false" data-sortable="false">{{ __('Action') }}</th>
                                    @endcanany
                                </tr>
                                </thead>
                            </table>
                        @elseif($type == 'providers')
                            <table class="table table-borderless table-striped" aria-describedby="mydesc"
                               id="table_list_providers" data-toggle="table" data-url="{{ route('category.show', ['category' => 0, 'type' => 'providers']) }}"
                               data-click-to-select="true" data-side-pagination="server" data-pagination="true"
                               data-page-list="[5, 10, 20, 50, 100, 200,500,2000]" data-search="true" data-search-align="right"
                               data-toolbar="#toolbar" data-show-columns="true" data-show-refresh="true"
                               data-trim-on-search="false" data-responsive="true" data-sort-name="sequence"
                               data-sort-order="asc" data-pagination-successively-size="3" data-query-params="queryParamsProviders"
                               data-escape="true"
                               data-table="categories" data-use-row-attr-func="true" data-mobile-responsive="false"
                               data-show-export="true" data-export-options='{"fileName": "category-list-providers","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']">
                                <thead class="thead-dark">
                                <tr>
                                    <th scope="col" data-field="id" data-align="center" data-sortable="true">{{ __('ID') }}</th>
                                    <th scope="col" data-field="name" data-sortable="true" data-formatter="categoryNameFormatter">{{ __('Name') }}</th>
                                    <th scope="col" data-field="image" data-align="center" data-formatter="imageFormatter">{{ __('Image') }}</th>
                                    <th scope="col" data-field="subcategories_count" data-align="center" data-sortable="true" data-formatter="subCategoryFormatter">{{ __('Subcategories') }}</th>
                                    <th scope="col" data-field="custom_fields_count" data-align="center" data-sortable="true" data-formatter="customFieldFormatter">{{ __('Custom Fields') }}</th>
                                    <th scope="col" data-field="items_count" data-sortable="true" data-align="center" data-formatter="">{{ __('Item Count') }}</th>
                                    @can('category-update')
                                        <th scope="col" data-field="status" data-width="5" data-sortable="true"  data-formatter="statusSwitchFormatter">{{ __('Active') }}</th>
                                    @endcan
                                    @canany(['category-update', 'category-delete'])
                                        <th scope="col" data-field="operate" data-escape="false" data-sortable="false">{{ __('Action') }}</th>
                                    @endcanany
                                </tr>
                                </thead>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    function queryParamsService(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            search: p.search,
            type: 'service_experience'
        };
    }
    
    function queryParamsProviders(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            search: p.search,
            type: 'providers'
        };
    }
    
    // Keep existing queryParams function for backward compatibility
    function queryParams(p) {
        return {
            limit: p.limit,
            sort: p.sort,
            order: p.order,
            offset: p.offset,
            search: p.search,
            type: '{{ $type ?? 'service_experience' }}'
        };
    }
</script>
@endpush
