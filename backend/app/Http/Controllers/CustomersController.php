<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserPurchasedPackage;
use App\Services\BootstrapTableService;
use App\Services\HelperService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;
use App\Models\Category;

class CustomersController extends Controller {
    public function index() {
        ResponseService::noAnyPermissionThenRedirect(['customer-list', 'customer-update']);
        $packages = Package::all()->where('status', 1);
        $settings = Setting::whereIn('name', ['currency_symbol', 'currency_symbol_position','free_ad_listing'])
        ->pluck('value', 'name');
        $currency_symbol = $settings['currency_symbol'] ?? '';
        $currency_symbol_position = $settings['currency_symbol_position'] ?? '';
        $free_ad_listing = $settings['free_ad_listing'] ?? '';
        $itemListingPackage = $packages->filter(function ($data) {
            return $data->type == "item_listing";
        });
        $advertisementPackage = $packages->filter(function ($data) {
            return $data->type == "advertisement";
        });

        // Get counts for each role
        $expertCount = User::role('Expert')->count();
        $businessCount = User::role('Business')->count();
        $clientCount = User::role('Client')->count();

        return view('customer.index', compact(
            'packages', 
            'itemListingPackage', 
            'advertisementPackage',
            'currency_symbol',
            'currency_symbol_position',
            'free_ad_listing',
            'expertCount',
            'businessCount',
            'clientCount'
        ));
    }

    public function update(Request $request) {
        try {
            ResponseService::noPermissionThenSendJson('customer-update');
            User::where('id', $request->id)->update(['status' => $request->status]);
            $message = $request->status ? "Customer Activated Successfully" : "Customer Deactivated Successfully";
            ResponseService::successResponse($message);
        } catch (Throwable) {
            ResponseService::errorRedirectResponse('Something Went Wrong ');
        }
    }

    public function show(Request $request) {
        ResponseService::noPermissionThenSendJson('customer-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';
        
        // Get the user role from the request, default to 'Client' if not specified
        $role = $request->role ?? 'Client';
        
        if ($request->notification_list) {
            $sql = User::role($role)->orderBy($sort, $order)->has('fcm_tokens')->where('notification', 1);
        } else {
            $sql = User::role($role);
        }

        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        
        // Include user_purchased_packages relationship for all roles
        $sql = $sql->with(['user_purchased_packages.package']);

        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($result as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['status'] = empty($row->deleted_at);
            $tempRow['is_verified'] = $row->is_verified;
            $tempRow['auto_approve_item'] = $row->auto_approve_item;
            $tempRow['role'] = $role;

            // Add active package information
            $activePackage = $row->user_purchased_packages
                ->where('status', 1)
                ->filter(function($package) {
                    // Start date is today or in the past
                    return $package->start_date <= date('Y-m-d') &&
                        // End date is null or in the future
                        ($package->end_date === null || $package->end_date > date('Y-m-d')) &&
                        // Used limit is less than total limit or total limit is null
                        ($package->total_limit === null || $package->used_limit < $package->total_limit);
                })
                ->first();
            
            $tempRow['has_active_package'] = !empty($activePackage);
            $tempRow['active_package_name'] = $activePackage ? ($activePackage->package->name ?? 'Unknown Package') : null;
            $tempRow['active_package_expiry'] = $activePackage ? $activePackage->end_date : null;

            if (config('app.demo_mode')) {
                // Get the first two digits, Apply enough asterisks to cover the middle numbers ,  Get the last two digits;
                if (!empty($row->mobile)) {
                    $tempRow['mobile'] = substr($row->mobile, 0, 3) . str_repeat('*', (strlen($row->mobile) - 5)) . substr($row->mobile, -2);
                }

                if (!empty($row->email)) {
                    $tempRow['email'] = substr($row->email, 0, 3) . '****' . substr($row->email, strpos($row->email, "@"));
                }
            }

            // Initialize operate as empty string
            $tempRow['operate'] = '';
            
            // For non-Client roles, add assign package button
            if ($role !== 'Client') {
                $tempRow['operate'] = BootstrapTableService::button(
                    'fa fa-cart-plus',
                    route('customer.assign.package', $row->id),
                    ['btn-outline-danger', 'assign_package'],
                    [
                        'title'          => __("Assign Package"),
                        "data-bs-target" => "#assignPackageModal",
                        "data-bs-toggle" => "modal",
                        "data-id"        => $row->id
                    ]
                );
                
                // Only add delete button for Expert and Business roles (no edit button)
                $tempRow['operate'] .= BootstrapTableService::button(
                    'fas fa-trash',
                    'javascript:void(0)',
                    ['btn-danger', 'delete-user'],
                    [
                        'title' => trans('Delete'),
                        'data-id' => $row->id
                    ]
                );
            } else {
                // For Client role, add both edit and delete buttons
                $tempRow['operate'] .= BootstrapTableService::editButton('javascript:void(0)', false, '', 'edit-user');
                $tempRow['operate'] .= BootstrapTableService::button(
                    'fas fa-trash',
                    'javascript:void(0)',
                    ['btn-danger', 'delete-user'],
                    [
                        'title' => trans('Delete'),
                        'data-id' => $row->id
                    ]
                );
            }
            
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    /**
     * List users by role
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listByRole(Request $request) {
        ResponseService::noPermissionThenSendJson('customer-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';
        
        // Get the user role from the request, default to 'Client' if not specified
        $role = $request->role ?? 'Client';
        
        // Debug the request parameters
        Log::info('List by role request', [
            'role' => $role,
            'offset' => $offset,
            'limit' => $limit,
            'sort' => $sort,
            'order' => $order,
            'search' => $request->search
        ]);
        
        if ($request->notification_list) {
            $sql = User::role($role)->orderBy($sort, $order)->has('fcm_tokens')->where('notification', 1);
        } else {
            $sql = User::role($role)->orderBy($sort, $order);
        }

        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }

        // We'll check for relationships in a safer way
        try {
            // Add relationships based on role if they exist
            if ($role === 'Expert' || $role === 'Business') {
                // For Experts and Business, include their services/experiences if the relationship exists
                $sql = $sql->with(['items', 'user_purchased_packages.package']);
            }
        } catch (\Exception $e) {
            Log::error('Error loading relationships: ' . $e->getMessage());
        }

        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        
        // Debug the result
        Log::info('List by role result', [
            'total' => $total,
            'count' => count($result)
        ]);
        
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;
        foreach ($result as $row) {
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['status'] = empty($row->deleted_at);
            $tempRow['is_verified'] = $row->is_verified ?? 0;
            $tempRow['auto_approve_item'] = $row->auto_approve_item ?? 0;
            $tempRow['role'] = $role;
            
            // Add active package information
            $activePackage = $row->user_purchased_packages
                ->where('status', 1)
                ->filter(function($package) {
                    // Start date is today or in the past
                    return $package->start_date <= date('Y-m-d') &&
                        // End date is null or in the future
                        ($package->end_date === null || $package->end_date > date('Y-m-d')) &&
                        // Used limit is less than total limit or total limit is null
                        ($package->total_limit === null || $package->used_limit < $package->total_limit);
                })
                ->first();
            
            $tempRow['has_active_package'] = !empty($activePackage);
            $tempRow['active_package_name'] = $activePackage ? ($activePackage->package->name ?? 'Unknown Package') : null;
            $tempRow['active_package_expiry'] = $activePackage ? $activePackage->end_date : null;

            // Add role-specific fields
            if ($role === 'Expert') {
                // Expert-specific fields
                $tempRow['gender'] = ''; // Not in database
                $tempRow['location'] = $row->address ?? '';
                $tempRow['categories'] = $row->categories ?? '';
                $tempRow['expertise'] = ''; // Not in database
                $tempRow['experience'] = ''; // Not in database
                $tempRow['services_count'] = isset($row->items) ? count($row->items) : 0;
                $tempRow['has_subscription'] = false; // Default to false since we can't check
            } elseif ($role === 'Business') {
                // Business-specific fields
                $tempRow['business_name'] = $row->name;
                $tempRow['location'] = $row->location ?? '';
                $tempRow['categories'] = $row->categories ?? '';
                $tempRow['phone'] = $row->phone ?? '';
                $tempRow['services_count'] = isset($row->items) ? count($row->items) : 0;
                $tempRow['has_subscription'] = false; // Default to false since we can't check
            } elseif ($role === 'Client') {
                // Client-specific fields
                $tempRow['gender'] = ''; // Not in database
                $tempRow['location'] = $row->address ?? '';
                $tempRow['bookings_count'] = 0; // Default to 0 since we can't check
            }

            if (config('app.demo_mode')) {
                // Get the first two digits, Apply enough asterisks to cover the middle numbers ,  Get the last two digits;
                if (!empty($row->mobile)) {
                    $tempRow['mobile'] = substr($row->mobile, 0, 3) . str_repeat('*', (strlen($row->mobile) - 5)) . substr($row->mobile, -2);
                }

                if (!empty($row->email)) {
                    $tempRow['email'] = substr($row->email, 0, 3) . '****' . substr($row->email, strpos($row->email, "@"));
                }
            }

            // Initialize operate as empty string
            $tempRow['operate'] = '';
            
            // For non-Client roles, add assign package button
            if ($role !== 'Client') {
                $tempRow['operate'] = BootstrapTableService::button(
                    'fa fa-cart-plus',
                    route('customer.assign.package', $row->id),
                    ['btn-outline-danger', 'assign_package'],
                    [
                        'title'          => __("Assign Package"),
                        "data-bs-target" => "#assignPackageModal",
                        "data-bs-toggle" => "modal",
                        "data-id"        => $row->id
                    ]
                );
                
                // Only add delete button for Expert and Business roles (no edit button)
                $tempRow['operate'] .= BootstrapTableService::button(
                    'fas fa-trash',
                    'javascript:void(0)',
                    ['btn-danger', 'delete-user'],
                    [
                        'title' => trans('Delete'),
                        'data-id' => $row->id
                    ]
                );
            } else {
                // For Client role, add both edit and delete buttons
                $tempRow['operate'] .= BootstrapTableService::editButton('javascript:void(0)', false, '', 'edit-user');
                $tempRow['operate'] .= BootstrapTableService::button(
                    'fas fa-trash',
                    'javascript:void(0)',
                    ['btn-danger', 'delete-user'],
                    [
                        'title' => trans('Delete'),
                        'data-id' => $row->id
                    ]
                );
            }
            
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        
        // Debug the final response
        Log::info('List by role response', [
            'total' => $bulkData['total'],
            'rows_count' => count($bulkData['rows'])
        ]);
        
        return response()->json($bulkData);
    }

    public function assignPackage(Request $request) {
        $validator = Validator::make($request->all(), [
            'package_id'      => 'required',
            'payment_gateway' => 'required|in:cash,cheque',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            ResponseService::noPermissionThenSendJson('customer-list');
            $user = User::find($request->user_id);
            if (empty($user)) {
                ResponseService::errorResponse('User not found');
            }
            
            // Check if user is soft deleted (inactive)
            if (!empty($user->deleted_at)) {
                ResponseService::errorResponse('User is not Active');
            }
            
            $package = Package::findOrFail($request->package_id);
            
            // Check if user already has an active package
            $existingPackage = UserPurchasedPackage::where('user_id', $request->user_id)
                ->where('status', 1)
                ->where('start_date', '<=', date('Y-m-d'))
                ->where(function ($q) {
                    $q->whereDate('end_date', '>', date('Y-m-d'))->orWhereNull('end_date');
                })
                ->first();
                
            if ($existingPackage) {
                // Update existing package status to 0 (expired)
                $existingPackage->update(['status' => 0]);
                
                // Log the replacement
                Log::info("User ID {$request->user_id} had existing package ID {$existingPackage->package_id} replaced with new package ID {$request->package_id}");
            }
            
            // Create a new payment transaction
            $paymentTransaction = PaymentTransaction::create([
                'user_id'         => $request->user_id,
                'package_id'      => $request->package_id,
                'amount'          => $package->final_price,
                'order_id'        => null,
                'payment_gateway' => $request->payment_gateway,
                'payment_status'  => 'succeed',
            ]);

            // Create a new user purchased package record
            UserPurchasedPackage::create([
                'user_id'                 => $request->user_id,
                'package_id'              => $request->package_id,
                'start_date'              => Carbon::now(),
                'end_date'                => $package->duration == "unlimited" ? null :Carbon::now()->addDays($package->duration),
                'total_limit'             => $package->item_limit == "unlimited" ? null : $package->item_limit,
                'used_limit'              => 0,
                'status'                  => 1,
                'payment_transactions_id' => $paymentTransaction->id,
            ]);
            DB::commit();
            ResponseService::successResponse('Package assigned to user Successfully');
        } catch (Throwable $th) {
            DB::rollback();
            ResponseService::logErrorResponse($th, "CustomersController --> assignPackage");
            ResponseService::errorResponse();
        }
    }

    /**
     * Display the clients view
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function clients()
    {
        ResponseService::noPermissionThenRedirect('customer-list');
        
        // Get package-related data
        $packages = Package::all()->where('status', 1);
        $settings = Setting::whereIn('name', ['currency_symbol', 'currency_symbol_position','free_ad_listing'])
            ->pluck('value', 'name');
        $currency_symbol = $settings['currency_symbol'] ?? '';
        $currency_symbol_position = $settings['currency_symbol_position'] ?? '';
        $free_ad_listing = $settings['free_ad_listing'] ?? '';
        $itemListingPackage = $packages->filter(function ($data) {
            return $data->type == "item_listing";
        });
        $advertisementPackage = $packages->filter(function ($data) {
            return $data->type == "advertisement";
        });
        
        return view('customer.clients', compact(
            'itemListingPackage', 
            'advertisementPackage',
            'currency_symbol',
            'currency_symbol_position',
            'free_ad_listing'
        ));
    }

    /**
     * Display the business view
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function business()
    {
        ResponseService::noPermissionThenRedirect('customer-list');
        
        // Get package-related data
        $packages = Package::all()->where('status', 1);
        $settings = Setting::whereIn('name', ['currency_symbol', 'currency_symbol_position','free_ad_listing'])
            ->pluck('value', 'name');
        $currency_symbol = $settings['currency_symbol'] ?? '';
        $currency_symbol_position = $settings['currency_symbol_position'] ?? '';
        $free_ad_listing = $settings['free_ad_listing'] ?? '';
        $itemListingPackage = $packages->filter(function ($data) {
            return $data->type == "item_listing";
        });
        $advertisementPackage = $packages->filter(function ($data) {
            return $data->type == "advertisement";
        });
        
        return view('customer.business', compact(
            'itemListingPackage', 
            'advertisementPackage',
            'currency_symbol',
            'currency_symbol_position',
            'free_ad_listing'
        ));
    }

    /**
     * Display the experts view
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function experts()
    {
        ResponseService::noPermissionThenRedirect('customer-list');
        
        // Get package-related data
        $packages = Package::all()->where('status', 1);
        $settings = Setting::whereIn('name', ['currency_symbol', 'currency_symbol_position','free_ad_listing'])
            ->pluck('value', 'name');
        $currency_symbol = $settings['currency_symbol'] ?? '';
        $currency_symbol_position = $settings['currency_symbol_position'] ?? '';
        $free_ad_listing = $settings['free_ad_listing'] ?? '';
        $itemListingPackage = $packages->filter(function ($data) {
            return $data->type == "item_listing";
        });
        $advertisementPackage = $packages->filter(function ($data) {
            return $data->type == "advertisement";
        });
        
        return view('customer.experts', compact(
            'itemListingPackage', 
            'advertisementPackage',
            'currency_symbol',
            'currency_symbol_position',
            'free_ad_listing'
        ));
    }

    /**
     * Update client information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateClient(Request $request)
    {
        try {
            ResponseService::noPermissionThenSendJson('customer-update');
            
            $validator = Validator::make($request->all(), [
                'id' => 'required|exists:users,id',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email,'.$request->id,
                'mobile' => 'nullable|string|max:20',
                'gender' => 'nullable|string|in:male,female,other',
                'address' => 'nullable|string|max:255',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }
            
            $user = User::findOrFail($request->id);
            
            // Ensure user has Client role
            if (!$user->hasRole('Client')) {
                return response()->json([
                    'error' => true,
                    'message' => 'User is not a client'
                ], 422);
            }
            
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'mobile' => $request->mobile,
                'gender' => $request->gender,
                'address' => $request->address,
            ]);
            
            return response()->json([
                'error' => false,
                'message' => 'Client updated successfully'
            ]);
            
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "CustomersController --> updateClient");
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while updating the client'
            ], 500);
        }
    }
    
    /**
     * Remove the specified user from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            ResponseService::noPermissionThenSendJson('customer-update');
            
            // Log the deletion attempt to help with debugging
            Log::info('User deletion attempt', [
                'user_id' => $id,
                'role' => request('role') ?? 'unknown',
                'requested_by' => auth()->id() ?? 'unauthenticated'
            ]);
            
            $user = User::withTrashed()->findOrFail($id);
            
            // Check if user is already deleted
            if ($user->trashed()) {
                return response()->json([
                    'error' => true,
                    'message' => 'User is already deleted'
                ], 400);
            }
            
            // Get the user's role for logging
            $userRoles = $user->getRoleNames()->toArray();
            Log::info('Deleting user with roles', [
                'user_id' => $id,
                'roles' => $userRoles
            ]);
            
            // Force flag to ensure deletion works
            $result = $user->delete();
            
            // Log the result
            Log::info('User deletion result', [
                'user_id' => $id,
                'result' => $result ? 'success' : 'failed'
            ]);
            
            return response()->json([
                'error' => false,
                'message' => 'User deleted successfully',
                'user_id' => $id,
                'roles' => $userRoles
            ]);
            
        } catch (Throwable $th) {
            // Enhanced error logging
            Log::error('Error deleting user', [
                'user_id' => $id,
                'exception' => $th->getMessage(),
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            
            ResponseService::logErrorResponse($th, "CustomersController --> destroy");
            return response()->json([
                'error' => true,
                'message' => 'An error occurred while deleting the user: ' . $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get category names by IDs
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategoryNames(Request $request)
    {
        ResponseService::noPermissionThenSendJson('customer-list');
        
        $categoryIds = explode(',', $request->ids);
        $categories = Category::whereIn('id', $categoryIds)->pluck('name', 'id')->toArray();
        
        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
