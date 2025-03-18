@extends('layouts.main')

@section('title')
    {{ __('Users') }}
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
        <div class="card">
            <div class="card-body">
                <!-- Tabs for different user roles -->
                <ul class="nav nav-tabs" id="userRoleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="client-tab" data-bs-toggle="tab" data-bs-target="#client" type="button" role="tab" aria-controls="client" aria-selected="true">
                            {{ __('Clients') }} <span class="badge bg-primary">{{ $clientCount }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="business-tab" data-bs-toggle="tab" data-bs-target="#business" type="button" role="tab" aria-controls="business" aria-selected="false">
                            {{ __('Business') }} <span class="badge bg-primary">{{ $businessCount }}</span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="expert-tab" data-bs-toggle="tab" data-bs-target="#expert" type="button" role="tab" aria-controls="expert" aria-selected="false">
                            {{ __('Experts') }} <span class="badge bg-primary">{{ $expertCount }}</span>
                        </button>
                    </li>
                </ul>

                <!-- Tab content -->
                <div class="tab-content" id="userRoleTabsContent">
                    <!-- Client Tab -->
                    <div class="tab-pane fade show active" id="client" role="tabpanel" aria-labelledby="client-tab">
                        <div class="row mt-3">
                            <div class="col-12">
                                <table class="table-borderless table-striped" aria-describedby="clientDesc" id="client_table"
                                       data-toggle="table" data-url="{{ route('customer.list-by-role', ['role' => 'Client']) }}" data-click-to-select="true"
                                       data-side-pagination="server" data-pagination="true"
                                       data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                       data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                       data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                                       data-responsive="true" data-sort-name="id" data-sort-order="desc"
                                       data-escape="true"
                                       data-pagination-successively-size="3" data-query-params="queryParamsClient" data-table="users" data-status-column="deleted_at"
                                       data-show-export="true" data-export-options='{"fileName": "client-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                                       data-mobile-responsive="true">
                                    <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="profile" data-formatter="imageFormatter">{{ __('Profile') }}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{ __('Full Name') }}</th>
                                        <th scope="col" data-field="email" data-sortable="true">{{ __('Email') }}</th>
                                        <th scope="col" data-field="gender" data-sortable="true">{{ __('Gender') }}</th>
                                        <th scope="col" data-field="location" data-sortable="true">{{ __('Location') }}</th>
                                        <th scope="col" data-field="mobile" data-sortable="true">{{ __('Mobile') }}</th>
                                        <th scope="col" data-field="bookings_count" data-sortable="true">{{ __('Bookings') }}</th>
                                        <th scope="col" data-field="status" data-formatter="statusSwitchFormatter" data-sortable="false">{{ __('Status') }}</th>
                                        <th scope="col" data-field="operate" data-escape="false" data-align="center" data-sortable="false" data-events="userEvents">{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Business Tab -->
                    <div class="tab-pane fade" id="business" role="tabpanel" aria-labelledby="business-tab">
                        <div class="row mt-3">
                            <div class="col-12">
                                <table class="table-borderless table-striped" aria-describedby="businessDesc" id="business_table"
                                       data-toggle="table" data-url="{{ route('customer.list-by-role', ['role' => 'Business']) }}" data-click-to-select="true"
                                       data-side-pagination="server" data-pagination="true"
                                       data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                       data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                       data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                                       data-responsive="true" data-sort-name="id" data-sort-order="desc"
                                       data-escape="true"
                                       data-pagination-successively-size="3" data-query-params="queryParamsBusiness" data-table="users" data-status-column="deleted_at"
                                       data-show-export="true" data-export-options='{"fileName": "business-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                                       data-mobile-responsive="true">
                                    <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="profile" data-formatter="imageFormatter">{{ __('Profile') }}</th>
                                        <th scope="col" data-field="business_name" data-sortable="true">{{ __('Business Name') }}</th>
                                        <th scope="col" data-field="email" data-sortable="true">{{ __('Email') }}</th>
                                        <th scope="col" data-field="mobile" data-sortable="true">{{ __('Mobile') }}</th>
                                        <th scope="col" data-field="location" data-sortable="true">{{ __('Location') }}</th>
                                        <th scope="col" data-field="categories" data-sortable="true">{{ __('Categories') }}</th>
                                        <th scope="col" data-field="services_count" data-sortable="true">{{ __('Services') }}</th>
                                        <th scope="col" data-field="has_subscription" data-formatter="subscriptionFormatter" data-sortable="true">{{ __('Subscription') }}</th>
                                        <th scope="col" data-field="status" data-formatter="statusSwitchFormatter" data-sortable="false">{{ __('Status') }}</th>
                                        <th scope="col" data-field="auto_approve_item" data-formatter="autoApproveItemSwitchFormatter" data-sortable="false">{{ __('Auto Approve Item') }}</th>
                                        <th scope="col" data-field="operate" data-escape="false" data-align="center" data-sortable="false" data-events="userEvents">{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Expert Tab -->
                    <div class="tab-pane fade" id="expert" role="tabpanel" aria-labelledby="expert-tab">
                        <div class="row mt-3">
                            <div class="col-12">
                                <table class="table-borderless table-striped" aria-describedby="expertDesc" id="expert_table"
                                       data-toggle="table" data-url="{{ route('customer.list-by-role', ['role' => 'Expert']) }}" data-click-to-select="true"
                                       data-side-pagination="server" data-pagination="true"
                                       data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true" data-toolbar="#toolbar"
                                       data-show-columns="true" data-show-refresh="true" data-fixed-columns="true"
                                       data-fixed-number="1" data-fixed-right-number="1" data-trim-on-search="false"
                                       data-responsive="true" data-sort-name="id" data-sort-order="desc"
                                       data-escape="true"
                                       data-pagination-successively-size="3" data-query-params="queryParamsExpert" data-table="users" data-status-column="deleted_at"
                                       data-show-export="true" data-export-options='{"fileName": "expert-list","ignoreColumn": ["operate"]}' data-export-types="['pdf','json', 'xml', 'csv', 'txt', 'sql', 'doc', 'excel']"
                                       data-mobile-responsive="true">
                                    <thead class="thead-dark">
                                    <tr>
                                        <th scope="col" data-field="id" data-sortable="true">{{ __('ID') }}</th>
                                        <th scope="col" data-field="profile" data-formatter="imageFormatter">{{ __('Profile') }}</th>
                                        <th scope="col" data-field="name" data-sortable="true">{{ __('Full Name') }}</th>
                                        <th scope="col" data-field="email" data-sortable="true">{{ __('Email') }}</th>
                                        <th scope="col" data-field="mobile" data-sortable="true">{{ __('Mobile') }}</th>
                                        <th scope="col" data-field="gender" data-sortable="true">{{ __('Gender') }}</th>
                                        <th scope="col" data-field="location" data-sortable="true">{{ __('Location') }}</th>
                                        <th scope="col" data-field="categories" data-sortable="true">{{ __('Categories') }}</th>
                                        <th scope="col" data-field="expertise" data-sortable="true">{{ __('Expertise') }}</th>
                                        <th scope="col" data-field="experience" data-sortable="true">{{ __('Experience') }}</th>
                                        <th scope="col" data-field="services_count" data-sortable="true">{{ __('Services') }}</th>
                                        <th scope="col" data-field="has_subscription" data-formatter="subscriptionFormatter" data-sortable="true">{{ __('Subscription') }}</th>
                                        <th scope="col" data-field="is_verified" data-formatter="verifiedFormatter" data-sortable="true">{{ __('Verified') }}</th>
                                        <th scope="col" data-field="status" data-formatter="statusSwitchFormatter" data-sortable="false">{{ __('Status') }}</th>
                                        <th scope="col" data-field="operate" data-escape="false" data-align="center" data-sortable="false" data-events="userEvents">{{ __('Action') }}</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div id="assignPackageModal" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="myModalLabel1"
             aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="myModalLabel1">{{ __('Assign Packages') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="resetModal()"></button>
                    </div>
                    <div class="modal-body">
                        <form class="create-form" action="{{ route('customer.assign.package') }}" method="POST" data-parsley-validate data-success-function="assignApprovalSuccess">
                            @csrf
                            <input type="hidden" name="user_id" id='user_id'>
                            <div id="currency-settings" data-symbol="{{ $currency_symbol }}"  data-position="{{ $currency_symbol_position }}" data-free-ad-listing="{{ $free_ad_listing }}"></div>
                            @if($free_ad_listing != 1)
                            <div class="form-group row select-package">
                                <div class="col-md-6">
                                    <input type="radio" id="item_package" class="package_type form-check-input" name="package_type" value="item_listing" required>
                                    <label for="item_package">{{ __('Item Listing Package') }}</label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" id="advertisement_package" class="package_type form-check-input" name="package_type" value="advertisement" required>
                                    <label for="advertisement_package">{{ __('Advertisement Package') }}</label>
                                </div>
                            </div>
                            @endif
                            <div class="row mt-3" id="item-listing-package-div" style="display: none;">
                                <div class="form-group col-md-12">
                                    <label for="package">{{__("Select Item Listing Package")}}</label>
                                    <select name="package_id" class="form-select package" id="item-listing-package" aria-label="Package">
                                        <option value="" disabled selected>Select Option</option>
                                        @foreach($itemListingPackage as $package)
                                            <option value="{{$package->id}}" data-details="{{json_encode($package)}}">{{$package->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="row mt-3" id="advertisement-package-div" style="{{ $free_ad_listing == '1' ? 'display: block;' : 'display: none;' }}">
                                <div class="form-group col-md-12">
                                    <label for="package">{{__("Select Advertisement Package")}}</label>
                                    <select name="package_id" class="form-select package" id="advertisement-package" aria-label="Package">
                                        <option value="" disabled selected>Select Option</option>
                                        @foreach($advertisementPackage as $package)
                                            <option value="{{$package->id}}" data-details="{{json_encode($package)}}">{{$package->name}}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div id="package_details" class="mt-3" style="display: none;">
                                <p><strong>Name:</strong> <span id="package_name"></span></p>
                                <p><strong>Price:</strong> <span id="package_price"></span></p>
                                <p><strong>Final Price:</strong> <span id="package_final_price"></span></p>
                                <p><strong>Limitation:</strong> <span id="package_duration"></span></p>
                            </div>
                            <div class="form-group row payment" style="display: none">
                                <div class="col-md-6">
                                    <input type="radio" id="cash_payment" class="payment_gateway form-check-input" name="payment_gateway" value="cash" required>
                                    <label for="cash_payment">{{ __('Cash') }}</label>
                                </div>
                                <div class="col-md-6">
                                    <input type="radio" id="cheque_payment" class="payment_gateway form-check-input" name="payment_gateway" value="cheque" required>
                                    <label for="cheque_payment">{{ __('Cheque') }}</label>
                                </div>
                            </div>
                            <div class="form-group cheque mt-3" style="display: none">
                                <label for="cheque">{{ __('Add cheque number') }}</label>
                                <input type="text" id="cheque" class="form-control" name="cheque_number" data-parsley-required="true">
                            </div>
                            <input type="submit" value="{{__("Save")}}" class="btn btn-primary mt-3">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
@section('js')
    <script>
        function assignApprovalSuccess() {
            $('#assignPackageModal').modal('hide');
        }
        function resetModal() {
            const modal = $('#assignPackageModal');
            const form = modal.find('form');
            form[0].reset();
        }
        
        // Query params for each role tab
        function queryParams(params) {
            return {
                limit: params.limit,
                offset: params.offset,
                search: params.search,
                sort: params.sort,
                order: params.order,
            };
        }
        
        function queryParamsClient(params) {
            const baseParams = queryParams(params);
            baseParams.role = 'Client';
            return baseParams;
        }
        
        function queryParamsBusiness(params) {
            const baseParams = queryParams(params);
            baseParams.role = 'Business';
            return baseParams;
        }
        
        function queryParamsExpert(params) {
            const baseParams = queryParams(params);
            baseParams.role = 'Expert';
            return baseParams;
        }
        
        // Formatter for verified status
        function verifiedFormatter(value, row) {
            if (value == 1) {
                return '<span class="badge bg-success">Verified</span>';
            } else {
                return '<span class="badge bg-danger">Not Verified</span>';
            }
        }
        
        // Formatter for subscription status
        function subscriptionFormatter(value, row) {
            if (value) {
                return '<span class="badge bg-success">Active</span>';
            } else {
                return '<span class="badge bg-warning">None</span>';
            }
        }
        
        // Initialize tables when tab is shown
        $(document).ready(function() {
            // Add response handler to debug and fix data issues
            $.fn.bootstrapTable.defaults.responseHandler = function(res) {
                console.log('Response received:', res);
                if (res && typeof res === 'object') {
                    // Ensure rows is an array
                    if (!res.rows || !Array.isArray(res.rows)) {
                        console.error('Invalid rows data:', res.rows);
                        res.rows = [];
                    }
                    
                    // Ensure total is a number
                    if (typeof res.total !== 'number') {
                        console.error('Invalid total:', res.total);
                        res.total = res.rows.length;
                    }
                    
                    return res;
                }
                
                console.error('Invalid response:', res);
                return { total: 0, rows: [] };
            };
            
            // Initialize all tables
            $('#client_table').bootstrapTable();
            $('#business_table').bootstrapTable();
            $('#expert_table').bootstrapTable();
            
            // Refresh the active tab's table
            $('#client_table').bootstrapTable('refresh');
            
            // Handle tab changes
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                const target = $(e.target).attr("data-bs-target");
                console.log('Tab changed to:', target);
                
                if (target === "#client") {
                    $('#client_table').bootstrapTable('refresh');
                } else if (target === "#business") {
                    $('#business_table').bootstrapTable('refresh');
                } else if (target === "#expert") {
                    $('#expert_table').bootstrapTable('refresh');
                }
            });
        });
    </script>
@endsection
