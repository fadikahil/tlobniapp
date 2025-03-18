@extends('layouts.main')
@section('title')
    @if(isset($type) && $type == 'providers')
        {{__("Change Provider Categories Order")}}
    @else
        {{__("Change Service & Experience Categories Order")}}
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
                <a class="btn btn-primary" href="{{ route('category.index', ['type' => 'providers']) }}">< {{__("Back to Provider Categories")}} </a>
            @else
                <a class="btn btn-primary" href="{{ route('category.index', ['type' => 'service_experience']) }}">< {{__("Back to Service & Experience Categories")}} </a>
            @endif
        </div>
        <div class="row">
            <div class="col-md-12 grid-margin stretch-card">
                <div class="card">
                    <div class="card-body">
                        <form class="pt-3" id="update-team-member-rank-form" action="{{ route('category.order.change')}}" novalidate="novalidate">
                            <input type="hidden" name="type" value="{{ $type ?? 'service_experience' }}">
                            <ul class="sortable row col-12 d-flex justify-content-center">
                                <div class="row bg-light pt-2 rounded mb-2 col-12 d-flex justify-content-center" id="categoriesList">
                                    <!-- Categories will be loaded dynamically based on type -->
                                </div>
                            </ul>
                            <input class="btn btn-primary" type="submit" value="Update"/>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        loadCategories();
        
        // Initialize sortable when categories are loaded
        $(function() {
            $(".sortable").sortable();
            $(".sortable").disableSelection();
        });
        
        // Handle form submission
        $('#update-team-member-rank-form').on('submit', function(e) {
            e.preventDefault();
            
            var idsArray = [];
            $(".sortable li").each(function() {
                idsArray.push($(this).attr('id'));
            });
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: {
                    ids: idsArray,
                    type: '{{ $type ?? 'service_experience' }}',
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(function() {
                            window.location.href = '{{ route('category.index', ['type' => $type ?? 'service_experience']) }}';
                        }, 1000);
                    } else {
                        toastr.error(response.message);
                    }
                },
                error: function(xhr) {
                    toastr.error('An error occurred while updating the order.');
                }
            });
        });
    });
    
    function loadCategories() {
        const categoriesList = document.getElementById('categoriesList');
        const type = '{{ $type ?? 'service_experience' }}';
        
        fetch('{{ url("category/get-parent-categories") }}?type=' + type)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (data.categories.length === 0) {
                        html = '<div class="col-12 text-center">No categories found.</div>';
                    } else {
                        data.categories.forEach(function(category) {
                            if(category.level === 0) { // Only show parent categories
                                html += `
                                    <li id="${category.id}" class="ui-state-default draggable col-md-12 col-lg-5 mr-2 col-xl-3" style="cursor:grab">
                                        <div class="bg-light pt-2 rounded mb-2 col-12 d-flex justify-content-center">
                                            <div class="row">
                                                <div class="col-6" style="padding-left: 15px; padding-right:5px;">
                                                    <img src="${category.image}" alt="image" class="order-change" onerror="this.src='{{ asset('assets/img_placeholder.jpeg') }}'"/>
                                                </div>
                                                <div class="col-6 d-flex flex-column justify-content-center align-items-center" style="padding-left: 5px; padding-right:5px;">
                                                    <strong>${category.name}</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                `;
                            }
                        });
                    }
                    
                    categoriesList.innerHTML = html;
                } else {
                    categoriesList.innerHTML = '<div class="col-12 text-center">Error loading categories.</div>';
                }
            })
            .catch(error => {
                categoriesList.innerHTML = '<div class="col-12 text-center">Error loading categories.</div>';
                console.error('Error loading categories:', error);
            });
    }
</script>
@endpush


