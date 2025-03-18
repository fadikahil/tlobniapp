<?php

namespace App\Http\Controllers;

use App\Http\Resources\ItemCollection;
use App\Models\Area;
use App\Models\BlockUser;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Chat;
use App\Models\City;
use App\Models\ContactUs;
use App\Models\Country;
use App\Models\CustomField;
use App\Models\Faq;
use App\Models\Favourite;
use App\Models\FeaturedItems;
use App\Models\FeatureSection;
use App\Models\Item;
use App\Models\ItemCustomFieldValue;
use App\Models\ItemImages;
use App\Models\ItemOffer;
use App\Models\Language;
use App\Models\Notifications;
use App\Models\Package;
use App\Models\PaymentConfiguration;
use App\Models\PaymentTransaction;
use App\Models\ReportReason;
use App\Models\SellerRating;
use App\Models\SeoSetting;
use App\Models\Setting;
use App\Models\Slider;
use App\Models\SocialLogin;
use App\Models\State;
use App\Models\Tip;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserPurchasedPackage;
use App\Models\UserReports;
use App\Models\VerificationField;
use App\Models\VerificationFieldRequest;
use App\Models\VerificationFieldValue;
use App\Models\VerificationRequest;
use App\Models\UserReview;
use App\Models\ServiceReview;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\NotificationService;
use App\Services\Payment\PaymentService;
use App\Services\ResponseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ApiController extends Controller
{

    private string $uploadFolder;

    public function __construct()
    {
        $this->uploadFolder = 'item_images';
        // if (array_key_exists('HTTP_AUTHORIZATION', $_SERVER) && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        // $this->middleware('auth:sanctum');
        // }
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        // Get the user's role
        $user->getRoleNames()->first();

        return response()->json([
            'status' => true,
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function getSystemSettings(Request $request)
    {
        try {
            $settings = Setting::select(['name', 'value', 'type']);

            if (!empty($request->type)) {
                $settings->where('name', $request->type);
            }

            $settings = $settings->get();

            foreach ($settings as $row) {
                if ($row->name == "place_api_key") {
                    /*TODO : Encryption will be done here*/
                    //$tempRow[$row->name] = HelperService::encrypt($row->value);
                    $tempRow[$row->name] = $row->value;
                } else {
                    $tempRow[$row->name] = $row->value;
                }
            }
            $language = CachingService::getLanguages();
            $tempRow['demo_mode'] = config('app.demo_mode');
            $tempRow['languages'] = $language;
            $tempRow['admin'] = User::role('Super Admin')->select(['name', 'profile'])->first();

            ResponseService::successResponse("Data Fetched Successfully", $tempRow);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getSystemSettings");
            ResponseService::errorResponse();
        }
    }

    public function userSignup(Request $request)
    {
        try {
            Log::info('User Signup Request', $request->all());
            // 1️⃣ Validate Request
            $validator = Validator::make($request->all(), [
                'email'         => 'required|string|email|unique:users,email',
                'password'      => 'required|string|min:6',
                'platform_type' => 'nullable|in:android,ios',
                'fullName'      => 'nullable|string',
                'gender'        => 'nullable|in:Male,Female,Other',
                'location'      => 'nullable|string',
                'userType'      => 'nullable|in:Provider,Client',
                'providerType'  => 'nullable|in:Expert,Business',
                'categories'    => 'nullable|string',
                'phone'         => 'nullable|string',
                'country_code'  => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return ResponseService::validationError($validator->errors()->first());
            }

            // Check if user exists and is deactivated
            $existingUser = User::withTrashed()->where('email', $request->email)->first();

            if ($existingUser && $existingUser->trashed()) {
                return ResponseService::errorResponse('Your account has been deactivated.', null, config('constants.RESPONSE_CODE.DEACTIVATED_ACCOUNT'));
            }

            DB::beginTransaction();

            // Create User
            $userData = [
                'password'      => bcrypt($request->password),
                'name'          => $request->fullName,
                'gender'        => $request->gender,
                'location'      => $request->location,
                'city'          => $request->city,
                'categories'    => $request->categories,
                'phone'         => $request->phone,
                'platform_type' => $request->platform_type,
                'type'          => $request->providerType, // Set type to 'email' by default
                'profile'       => $request->hasFile('profile')
                    ? $request->file('profile')->store('user_profile', 'public')
                    : null,
            ];

            $user = User::updateOrCreate(
                ['email' => $request->email],
                $userData
            );

            // Assign Role Based on User Type
            if ($request->userType === 'Provider') {
                if ($request->providerType === 'Expert') {
                    $user->syncRoles(['Expert']);
                } elseif ($request->providerType === 'Business') {
                    $user->syncRoles(['Business']);
                }
            } else {
                // Assign "Client" role for non-providers
                $user->syncRoles(['Client']);
            }

            // Log In User
            Auth::login($user);

            // Generate Token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            // Get the user's role
            $user->getRoleNames()->first();

            return response()->json([
                'status' => true,
                'token' => $token,
                'user' => $user,
            ]);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> Signup");
            return ResponseService::errorResponse();
        }
    }

    public function updateProfile(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'                  => 'nullable|string',
                'fullName'              => 'nullable|string',
                'profile'               => 'nullable|mimes:jpg,jpeg,png|max:6144',
                'email'                 => 'nullable|email|unique:users,email,' . Auth::user()->id,
                'mobile'                => 'nullable|unique:users,mobile,' . Auth::user()->id,
                'fcm_id'                => 'nullable',
                'address'               => 'nullable',
                'show_personal_details' => 'boolean',
                'country_code'          => 'nullable|string',
                'gender'                => 'nullable|in:Male,Female,Other',
                'location'              => 'nullable|string',
                'country'               => 'nullable|string', // Keep for backward compatibility
                'userType'              => 'nullable|in:Provider,Client',
                'providerType'          => 'nullable|in:Expert,Business',
                'businessName'          => 'nullable|string',
                'categories'            => 'nullable|string',
                'phone'                 => 'nullable|string',
                'city'                  => 'nullable|string'
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $app_user = Auth::user();
            $data = $app_user->type == "google" ? $request->except('email') : $request->all();

            // Map fullName to name if provided
            if (!empty($request->fullName)) {
                $data['name'] = $request->fullName;
            }
            
            if (!empty($request->phone)) {
                $data['mobile'] = $request->phone;
            }

            if (isset($request->categories)) {
                $data['categories'] = $request->categories;
            }

            if (isset($request->country)) {
                $data['location'] = $request->country;
                unset($data['country']); 
            }
            
            if ($request->hasFile('profile')) {
                $originalProfile = DB::table('users')->where('id', $app_user->id)->value('profile');
                $data['profile'] = FileService::compressAndReplace($request->file('profile'), 'profile', $originalProfile);
            }

            if (!empty($request->fcm_id)) {
                UserFcmToken::updateOrCreate(['fcm_token' => $request->fcm_id], ['user_id' => $app_user->id, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()]);
            }
            
            // Set show_personal_details if provided
            if (isset($request->show_personal_details)) {
                $data['show_personal_details'] = $request->show_personal_details;
            }

            // Handle role changes if userType is provided
            if (!empty($request->userType)) {
                $user = User::find($app_user->id);
                
                if ($request->userType === 'Provider') {
                    if ($request->providerType === 'Expert') {
                        $user->syncRoles(['Expert']);
                        $data['provider_type'] = 'Expert';
                    } elseif ($request->providerType === 'Business') {
                        $user->syncRoles(['Business']);
                        $data['provider_type'] = 'Business';
                    }
                } else {
                    // Assign "Client" role for non-providers
                    $user->syncRoles(['Client']);
                    $data['provider_type'] = null;
                }
            }

            DB::table('users')->where('id', $app_user->id)->update($data);

            $updatedUser = User::find($app_user->id);
            
            $updatedUser->getRoleNames()->first();

            ResponseService::successResponse("Profile Updated Successfully", $updatedUser);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> updateProfile');
            ResponseService::errorResponse();
        }
    }

    public function getPackage(Request $request)
    {
        $validator = Validator::make($request->toArray(), [
            'platform' => 'nullable|in:android,ios',
            'type'     => 'nullable|in:advertisement,item_listing'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $packages = Package::where('status', 1);

            if (Auth::check()) {
                $packages = $packages->with('user_purchased_packages', function ($q) {
                    $q->onlyActive();
                });
            }

            if (isset($request->platform) && $request->platform == "ios") {
                $packages->whereNotNull('ios_product_id');
            }

            if (!empty($request->type)) {
                $packages = $packages->where('type', $request->type);
            }
            $packages = $packages->orderBy('id', 'ASC')->get();

            $packages->map(function ($package) {
                if (Auth::check()) {
                    $package['is_active'] = count($package->user_purchased_packages) > 0;
                } else {
                    $package['is_active'] = false;
                }
                return $package;
            });
            ResponseService::successResponse('Data Fetched Successfully', $packages);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getPackage");
            ResponseService::errorResponse();
        }
    }

    public function assignFreePackage(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_id' => 'required|exists:packages,id',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $user = Auth::user();

            $package = Package::where(['final_price' => 0, 'id' => $request->package_id])->firstOrFail();
            $activePackage = UserPurchasedPackage::where(['package_id' => $request->package_id, 'user_id' => Auth::user()->id])->first();
            if (!empty($activePackage)) {
                ResponseService::errorResponse("You already have applied for this package");
            }

            UserPurchasedPackage::create([
                'user_id'     => $user->id,
                'package_id'  => $request->package_id,
                'start_date'  => Carbon::now(),
                'total_limit' => $package->item_limit == "unlimited" ? null : $package->item_limit,
                'end_date'    => $package->duration == "unlimited" ? null : Carbon::now()->addDays($package->duration),
                'status'      => 0  
            ]);
            ResponseService::successResponse('Package has been successfully assigned to your account.');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> assignFreePackage");
            ResponseService::errorResponse();
        }
    }

    public function getLimits(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'package_type' => 'required|in:item_listing,advertisement',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $setting = Setting::where('name', "free_ad_listing")->first()['value'];
            if ($setting == 1) {
                return ResponseService::successResponse("User is allowed to create Item");
            }
            $user_package = UserPurchasedPackage::onlyActive()->whereHas('package', function ($q) use ($request) {
                $q->where('type', $request->package_type);
            })->count();
            if ($user_package > 0) {
                ResponseService::successResponse("User is allowed to create Item");
            }

            // Check if user has pending packages
            $pending_package = UserPurchasedPackage::where('user_id', Auth::user()->id)
                ->where('status', 0) // Pending packages
                ->whereHas('package', function ($q) use ($request) {
                    $q->where('type', $request->package_type);
                })->count();

            if ($pending_package > 0) {
                ResponseService::errorResponse("User is pending", $pending_package);
            }
            ResponseService::errorResponse("User is not allowed to create Item", $user_package);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getLimits");
            ResponseService::errorResponse();
        }
    }

    public function addItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'                 => 'required',
                'category_id'          => 'required|integer',
                'price'                => 'required',
                'description'          => 'nullable',
                'address'              => 'nullable',
                'contact'              => 'nullable|numeric',
                'show_only_to_premium' => 'nullable|boolean',
                'video_link'           => 'nullable|url',
                'gallery_images'       => 'nullable|array|min:1',
                'gallery_images.*'     => 'nullable|mimes:jpeg,png,jpg|max:6144',
                'image'                => 'nullable|mimes:jpeg,png,jpg|max:6144',
                'country'              => 'nullable',
                'state'                => 'nullable',
                'city'                 => 'nullable',
                'custom_field_files'   => 'nullable|array',
                'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:6144',
                'slug'                 => 'nullable|regex:/^[a-z0-9-]+$/',
                'provider_item_type'   => 'nullable|in:service,experience',
                'post_type'            => 'nullable|string',
                'price_type'           => 'nullable|string',
                'special_tags'         => 'nullable',
                'location_type'        => 'nullable|string',
                'expiration_date'      => 'nullable|date',
                'expiration_time'      => 'nullable|string',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            DB::beginTransaction();
            $user = Auth::user();
            
            // Determine provider_item_type based on post_type parameter
            $providerItemType = 'service'; // Default value
            if ($request->post_type === 'PostType.experience') {
                $providerItemType = 'experience';
            }
            
            $user_package = UserPurchasedPackage::onlyActive()->whereHas('package', static function ($q) {
                $q->where('type', 'item_listing');
            })->where('user_id', $user->id)->first();
            $free_ad_listing = Setting::where('name', "free_ad_listing")->first()['value'];
            if ($user->auto_approve_item == 1) {
                $status = "approved";
            } else {
                $status = "review";
            }
            if ($free_ad_listing == 0 && empty($user_package)) {
                ResponseService::errorResponse("No Active Package found for Item Creation");
            }
            if ($user_package) {
                ++$user_package->used_limit;
                $user_package->save();
            }

            $slug = $request->input('slug');
            if (empty($slug)) {
                $slug = HelperService::generateRandomSlug();
            }
            $uniqueSlug = HelperService::generateUniqueSlug(new Item(), $slug);

            // Handle expiry date differently based on provider_item_type
            $expiryDate = null;
            if ($providerItemType === 'experience' && $request->expiration_date) {
                // For experience type, use the provided expiration date and time
                $expiryDate = $request->expiration_date;
                
                // If expiration_time is provided, combine it with the expiration_date
                if ($request->expiration_time) {
                    $dateTime = new \DateTime($expiryDate);
                    list($hours, $minutes) = explode(':', $request->expiration_time);
                    $dateTime->setTime((int)$hours, (int)$minutes);
                    $expiryDate = $dateTime->format('Y-m-d H:i:s');
                }
            } else {
                // For service type (default), use the package end date
                $expiryDate = $user_package->end_date ?? null;
            }

            // Process special_tags if provided
            $specialTags = null;
            if ($request->special_tags) {
                $specialTags = is_array($request->special_tags) 
                    ? json_encode($request->special_tags) 
                    : $request->special_tags;
            }

            // Set default values for optional fields if not provided
            $data = [
                'name'                 => $request->name,
                'slug'                 => $uniqueSlug,
                'status'               => $status,
                'active'               => "deactive",
                'user_id'              => $user->id,
                'package_id'           => $user_package->package_id ?? '',
                'expiry_date'          => $expiryDate,
                'category_id'          => $request->category_id,
                'price'                => $request->price,
                'price_type'           => $request->price_type ?? null,
                'special_tags'         => $specialTags,
                'description'          => $request->description ?? '',
                'address'              => $request->address ?? '',
                'contact'              => $request->contact ?? null,
                'show_only_to_premium' => $request->show_only_to_premium ?? 0,
                'video_link'           => $request->video_link ?? null,
                'country'              => $request->country ?? '',
                'state'                => $request->state ?? null,
                'city'                 => $request->city ?? '',
                'location_type'        => $request->location_type ?? null,
                'area_id'              => $request->area_id ?? null,
                'provider_item_type'   => $providerItemType,
                'expiration_date'      => $request->expiration_date ?? null,
                'expiration_time'      => $request->expiration_time ?? null,
            ];
            
            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);
            }
            $item = Item::create($data);

            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImages[] = [
                        'image'      => FileService::compressAndUpload($file, $this->uploadFolder),
                        'item_id'    => $item->id,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }

                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }
            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                foreach (json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR) as $key => $custom_field) {
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => json_encode($custom_field, JSON_THROW_ON_ERROR),
                        'created_at'      => time(),
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => !empty($file) ? FileService::upload($file, 'custom_fields_files') : '',
                        'created_at'      => time(),
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::insert($itemCustomFieldValues);
                }
            }

            // Add where condition here
            $result = Item::with(
                'user:id,name,email,mobile,profile,country_code',
                'category:id,name,image',
                'gallery_images:id,image,item_id',
                'featured_items',
                'favourites',
                'item_custom_field_values.custom_field',
                'area'
            )->where('id', $item->id)->get();
            $result = new ItemCollection($result);

            DB::commit();
            ResponseService::successResponse("Item Added Successfully", $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> addItem");
            ResponseService::errorResponse();
        }
    }

    public function getItem(Request $request)
    {
        Log::info('getItem called with parameters:', $request->all());
        $validator = Validator::make($request->all(), [
            'limit'         => 'nullable|integer',
            'offset'        => 'nullable|integer',
            'id'            => 'nullable',
            'custom_fields' => 'nullable',
            'category_id'   => 'nullable',
            'user_id'       => 'nullable',
            'min_price'     => 'nullable',
            'max_price'     => 'nullable',
            'sort_by'       => 'nullable|in:new-to-old,old-to-new,price-high-to-low,price-low-to-high,popular_items',
            'posted_since'  => 'nullable|in:all-time,today,within-1-week,within-2-week,within-1-month,within-3-month',
            'service_type'  => 'nullable|in:exclusive_experience,service',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        
        try {
            // Start with a query builder including all necessary relations
            $sql = Item::with('user:id,name,email,mobile,profile,created_at,is_verified,show_personal_details,country_code', 
                            'category:id,name,image', 
                            'gallery_images:id,image,item_id', 
                            'featured_items', 
                            'favourites', 
                            'item_custom_field_values.custom_field', 
                            'area:id,name')
                ->withCount('favourites')
                ->select('items.*')
                ->where('status', 'approved')
                ->whereNull('deleted_at');

            // Add non-expired filter
            $sql->where(function($query) {
                $query->where('expiry_date', '>', now())
                    ->orWhereNull('expiry_date');
            });
            
            // Basic filters
            if ($request->filled('id')) {
                $sql->where('id', $request->id);
            }
            
            if ($request->filled('category_id')) {
                $category = Category::where('id', $request->category_id)->with('children')->get();
                if ($category->isNotEmpty()) {
                    $categoryIDS = HelperService::findAllCategoryIds($category);
                    if (!empty($categoryIDS)) {
                        $sql->whereIn('category_id', $categoryIDS);
                    }
                }
            }
            
            if ($request->filled('user_id')) {
                $sql->where('user_id', $request->user_id);
            }
            
            // Service type filter
            if ($request->filled('service_type')) {
                Log::info('Filtering by service_type: ' . $request->service_type);
                
                if ($request->service_type === 'exclusive_experience') {
                    $sql->where('provider_item_type', 'experience');
                } elseif ($request->service_type === 'service') {
                    $sql->where(function($query) {
                        $query->where('provider_item_type', 'service')
                              ->orWhereNull('provider_item_type')
                              ->orWhere('provider_item_type', '');
                    });
                }
            }
            
            // Price filters
            if ($request->filled('min_price') || $request->filled('max_price')) {
                $min_price = $request->filled('min_price') ? $request->min_price : 0;
                $max_price = $request->filled('max_price') ? $request->max_price : Item::max('price');
                $sql->whereBetween('price', [$min_price, $max_price]);
            }
            
            // Skip special_tags filtering to show all items
            if ($request->filled('special_tags') && is_array($request->special_tags)) {
                Log::info('Special tags in request, but ignoring for filtering to show all items:', $request->special_tags);
            }

            // Get counts and results
            $count = $sql->count();
            Log::info('Total item count before pagination: ' . $count);
            
            $result = $request->filled('id') ? $sql->get() : $sql->paginate();
            Log::info('Result count: ' . $result->count());

            // Transform results to add properties
            if ($result instanceof \Illuminate\Pagination\LengthAwarePaginator || $result instanceof \Illuminate\Database\Eloquent\Collection) {
                $result->transform(function ($item) {
                    // Add service_type based on provider_item_type
                    $item->service_type = $this->determineServiceType($item);
                    
                    // Add user_type if user relation is loaded
                    if ($item->relationLoaded('user') && $item->user) {
                        $item->user_type = $this->determineUserType($item->user);
                    }
                    
                    // Process special_tags for display
                    $item->special_tags = $this->determineSpecialTags($item);
                    
                    return $item;
                });
            }

            ResponseService::successResponse("Item Fetched Successfully", new ItemCollection($result));
        } catch (Throwable $th) {
            Log::error('Exception in getItem: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            ResponseService::logErrorResponse($th, "API Controller -> getItem");
            ResponseService::errorResponse();
        }
    }

    public function updateItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'                   => 'required',
            'name'                 => 'nullable',
            'slug'                 => 'regex:/^[a-z0-9-]+$/',
            'price'                => 'nullable',
            'description'          => 'nullable',
            'latitude'             => 'nullable',
            'longitude'            => 'nullable',
            'address'              => 'nullable',
            'contact'              => 'nullable',
            'image'                => 'nullable|mimes:jpeg,jpg,png|max:6144',
            'custom_fields'        => 'nullable',
            'custom_field_files'   => 'nullable|array',
            'custom_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:6144',
            'gallery_images'       => 'nullable|array',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        DB::beginTransaction();

        try {

            $item = Item::owner()->findOrFail($request->id);

            $slug = $request->input('slug', $item->slug);
            $uniqueSlug = HelperService::generateUniqueSlug(new Item(), $slug, $request->id);

            $data = $request->all();
            $data['slug'] = $uniqueSlug;
            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndReplace($request->file('image'), $this->uploadFolder, $item->getRawOriginal('image'));
            }

            $item->update($data);

            //Update Custom Field values for item
            if ($request->custom_fields) {
                $itemCustomFieldValues = [];
                foreach (json_decode($request->custom_fields, true, 512, JSON_THROW_ON_ERROR) as $key => $custom_field) {
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => json_encode($custom_field, JSON_THROW_ON_ERROR),
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
                }
            }

            //Add new gallery images
            if ($request->hasFile('gallery_images')) {
                $galleryImages = [];
                foreach ($request->file('gallery_images') as $file) {
                    $galleryImages[] = [
                        'image'      => FileService::compressAndUpload($file, $this->uploadFolder),
                        'item_id'    => $item->id,
                        'created_at' => time(),
                        'updated_at' => time(),
                    ];
                }
                if (count($galleryImages) > 0) {
                    ItemImages::insert($galleryImages);
                }
            }

            if ($request->custom_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->custom_field_files as $key => $file) {
                    $value = ItemCustomFieldValue::where(['item_id' => $item->id, 'custom_field_id' => $key])->first();
                    if (!empty($value)) {
                        $file = FileService::replace($file, 'custom_fields_files', $value->getRawOriginal('value'));
                    } else {
                        $file = '';
                    }
                    $itemCustomFieldValues[] = [
                        'item_id'         => $item->id,
                        'custom_field_id' => $key,
                        'value'           => $file,
                        'updated_at'      => time()
                    ];
                }

                if (count($itemCustomFieldValues) > 0) {
                    ItemCustomFieldValue::upsert($itemCustomFieldValues, ['item_id', 'custom_field_id'], ['value', 'updated_at']);
                }
            }

            //Delete gallery images
            if (!empty($request->delete_item_image_id)) {
                $item_ids = explode(',', $request->delete_item_image_id);
                foreach (ItemImages::whereIn('id', $item_ids)->get() as $itemImage) {
                    FileService::delete($itemImage->getRawOriginal('image'));
                    $itemImage->delete();
                }
            }

            $result = Item::with('user:id,name,email,mobile,profile,country_code', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field', 'area')->where('id', $item->id)->get();
            /*
             * Collection does not support first OR find method's result as of now. It's a part of R&D
             * So currently using this shortcut method
            */
            $result = new ItemCollection($result);


            DB::commit();
            ResponseService::successResponse("Item Fetched Successfully", $result);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> updateItem");
            ResponseService::errorResponse();
        }
    }

    public function deleteItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                ResponseService::errorResponse($validator->errors()->first());
            }
            $item = Item::owner()->with('gallery_images')->withTrashed()->findOrFail($request->id);
            FileService::delete($item->getRawOriginal('image'));

            if (count($item->gallery_images) > 0) {
                foreach ($item->gallery_images as $key => $value) {
                    FileService::delete($value->getRawOriginal('image'));
                }
            }

            $item->forceDelete();
            ResponseService::successResponse("Item Deleted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> deleteItem");
            ResponseService::errorResponse();
        }
    }

    public function updateItemStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            'status'  => 'required|in:sold out,inactive,active',
            // 'sold_to' => 'required_if:status,==,sold out|integer'
            'sold_to' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::owner()->whereNotIn('status', ['review', 'rejected'])->withTrashed()->findOrFail($request->item_id);
            if ($request->status == "inactive") {
                $item->delete();
            } else if ($request->status == "active") {
                $item->restore();
                $item->update(['status' => 'review']);
            } else if ($request->status == "sold out") {
                $item->update([
                    'status'  => 'sold out',
                    'sold_to' => $request->sold_to
                ]);
            } else {
                $item->update(['status' => $request->status]);
            }
            ResponseService::successResponse('Item Status Updated Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'ItemController -> updateItemStatus');
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getItemBuyerList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $buyer_ids = ItemOffer::where('item_id', $request->item_id)->select('buyer_id')->pluck('buyer_id');
            $users = User::select(['id', 'name', 'profile'])->whereIn('id', $buyer_ids)->get();
            ResponseService::successResponse('Buyer List fetched Successfully', $users);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'ItemController -> updateItemStatus');
            ResponseService::errorResponse('Something Went Wrong');
        }
    }

    public function getSubCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = Category::withCount(['subcategories' => function ($q) {
                $q->where('status', 1);
            }])->with('translations')->where(['status' => 1])->orderBy('sequence', 'ASC')
                ->with(['subcategories'          => function ($query) {
                    $query->where('status', 1)->orderBy('sequence', 'ASC')->with('translations')->withCount(['approved_items', 'subcategories' => function ($q) {
                        $q->where('status', 1);
                    }]); // Order subcategories by 'sequence'
                }, 'subcategories.subcategories' => function ($query) {
                    $query->where('status', 1)->orderBy('sequence', 'ASC')->with('translations')->withCount(['approved_items', 'subcategories' => function ($q) {
                        $q->where('status', 1);
                    }]);
                }]);
            if (!empty($request->category_id)) {
                $sql = $sql->where('parent_category_id', $request->category_id);
            } else if (!empty($request->slug)) {
                $parentCategory = Category::where('slug', $request->slug)->firstOrFail();
                $sql = $sql->where('parent_category_id', $parentCategory->id);
            } else {
                $sql = $sql->whereNull('parent_category_id');
            }

            $sql = $sql->paginate();
            $sql->map(function ($category) {
                $category->all_items_count = $category->all_items_count;
                return $category;
            });
            ResponseService::successResponse(null, $sql, ['self_category' => $parentCategory ?? null]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
            ResponseService::errorResponse();
        }
    }

    public function getParentCategoryTree(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'child_category_id' => 'nullable|integer',
            'tree'              => 'nullable|boolean',
            'slug'              => 'nullable|string'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $sql = Category::when($request->child_category_id, function ($sql) use ($request) {
                $sql->where('id', $request->child_category_id);
            })
                ->when($request->slug, function ($sql) use ($request) {
                    $sql->where('slug', $request->slug);
                })
                ->firstOrFail()
                ->ancestorsAndSelf()->breadthFirst()->get();
            if ($request->tree) {
                $sql = $sql->toTree();
            }
            ResponseService::successResponse(null, $sql);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getCategories');
            ResponseService::errorResponse();
        }
    }

    public function getNotificationList()
    {
        try {
            $notifications = Notifications::whereRaw("FIND_IN_SET(" . Auth::user()->id . ",user_id)")->orWhere('send_to', 'all')->orderBy('id', 'DESC')->paginate();
            ResponseService::successResponse("Notification fetched successfully", $notifications);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getNotificationList');
            ResponseService::errorResponse();
        }
    }

    public function getLanguages(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'language_code' => 'required',
                'type'          => 'nullable|in:app,web'
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $language = Language::where('code', $request->language_code)->firstOrFail();
            if ($request->type == "web") {
                $json_file_path = base_path('resources/lang/' . $request->language_code . '_web.json');
            } else {
                $json_file_path = base_path('resources/lang/' . $request->language_code . '_app.json');
            }

            if (!is_file($json_file_path)) {
                ResponseService::errorResponse("Language file not found");
            }

            $json_string = file_get_contents($json_file_path);
            $json_data = json_decode($json_string, false, 512, JSON_THROW_ON_ERROR);

            if ($json_data == null) {
                ResponseService::errorResponse("Invalid JSON format in the language file");
            }
            $language->file_name = $json_data;

            ResponseService::successResponse("Data Fetched Successfully", $language);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getLanguages");
            ResponseService::errorResponse();
        }
    }

    public function appPaymentStatus(Request $request)
    {
        try {
            $paypalInfo = $request->all();
            if (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "completed") {
                ResponseService::successResponse("Your Package will be activated within 10 Minutes", $paypalInfo['txn_id']);
            } elseif (!empty($paypalInfo) && isset($_GET['st']) && strtolower($_GET['st']) == "authorized") {
                ResponseService::successResponse("Your Transaction is Completed. Ads wil be credited to your account within 30 minutes.", $paypalInfo);
            } else {
                ResponseService::errorResponse("Payment Cancelled / Declined ", (isset($_GET)) ? $paypalInfo : "");
            }
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> appPaymentStatus");
            ResponseService::errorResponse();
        }
    }

    public function getPaymentSettings()
    {
        try {
            // Instead of querying payment configurations, return a simple cash payment option
            $response = [
                'Cash' => [
                    'currency_code' => 'USD', // Use your preferred currency
                    'payment_method' => 'Cash',
                    'api_key' => '',
                    'status' => 1
                ]
            ];
            
            ResponseService::successResponse("Data Fetched Successfully", $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getPaymentSettings");
            ResponseService::errorResponse();
        }
    }

    public function getCustomFields(Request $request)
    {
        try {
            $customField = CustomField::whereHas('custom_field_category', function ($q) use ($request) {
                $q->whereIn('category_id', explode(',', $request->input('category_ids')));
            })->where('status', 1)->get();
            ResponseService::successResponse("Data Fetched successfully", $customField);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getCustomFields");
            ResponseService::errorResponse();
        }
    }

    public function makeFeaturedItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::commit();
            $user = Auth::user();
            Item::where('status', 'approved')->findOrFail($request->item_id);
            $user_package = UserPurchasedPackage::onlyActive()->where(['user_id' => $user->id])->with('package')
                ->whereHas('package', function ($q) {
                    $q->where(['type' => 'advertisement']);
                })->firstOrFail();

            $featuredItems = FeaturedItems::where(['item_id' => $request->item_id, 'package_id' => $user_package->package_id])->first();
            if (!empty($featuredItems)) {
                ResponseService::errorResponse("Item is already featured");
            }

            ++$user_package->used_limit;
            $user_package->save();

            FeaturedItems::create([
                'item_id'                   => $request->item_id,
                'package_id'                => $user_package->package_id,
                'user_purchased_package_id' => $user_package->id,
                'start_date'                => date('Y-m-d'),
                'end_date'                  => $user_package->end_date
            ]);

            DB::commit();
            ResponseService::successResponse("Featured Item Created Successfully");
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> createAdvertisement");
            ResponseService::errorResponse();
        }
    }

    public function manageFavourite(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $favouriteItem = Favourite::where('user_id', Auth::user()->id)->where('item_id', $request->item_id)->first();
            if (empty($favouriteItem)) {
                $favouriteItem = new Favourite();
                $favouriteItem->user_id = Auth::user()->id;
                $favouriteItem->item_id = $request->item_id;
                $favouriteItem->save();
                ResponseService::successResponse("Item added to Favourite");
            } else {
                $favouriteItem->delete();
                ResponseService::successResponse("Item remove from Favourite");
            }
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> manageFavourite");
            ResponseService::errorResponse();
        }
    }

    public function getFavouriteItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable|integer',
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $favouriteItemIDS = Favourite::where('user_id', Auth::user()->id)->select('item_id')->pluck('item_id');
            $items = Item::whereIn('id', $favouriteItemIDS)
                ->with('user:id,name,email,mobile,profile,is_verified,show_personal_details,country_code', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field')->where('status', '<>', 'sold out')->paginate();

            ResponseService::successResponse("Data Fetched Successfully", new ItemCollection($items));
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getFavouriteItem");
            ResponseService::errorResponse();
        }
    }

    public function getSlider()
    {
        try {
            $rows = Slider::with(['model' => function (MorphTo $morphTo) {
                $morphTo->constrain([Category::class => function ($query) {
                    $query->withCount('subcategories');
                }]);
            }])->get();
            ResponseService::successResponse(null, $rows);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getSlider");
            ResponseService::errorResponse();
        }
    }

    public function getReportReasons(Request $request)
    {
        try {
            $report_reason = new ReportReason();
            if (!empty($request->id)) {
                $id = $request->id;
                $report_reason->where('id', '=', $id);
            }
            $result = $report_reason->paginate();
            $total = $report_reason->count();
            ResponseService::successResponse("Data Fetched Successfully", $result, ['total' => $total]);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getReportReasons");
            ResponseService::errorResponse();
        }
    }

    public function addReports(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id'          => 'required',
                'report_reason_id' => 'required_without:other_message',
                'other_message'    => 'required_without:report_reason_id'
            ]);
            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $user = Auth::user();
            $report_count = UserReports::where('item_id', $request->item_id)->where('user_id', $user->id)->first();
            if ($report_count) {
                ResponseService::errorResponse("Already Reported");
            }
            UserReports::create([
                ...$request->all(),
                'user_id'       => $user->id,
                'other_message' => $request->other_message ?? '',
            ]);
            ResponseService::successResponse("Report Submitted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> addReports");
            ResponseService::errorResponse();
        }
    }

    public function setItemTotalClick(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'item_id' => 'required',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            Item::findOrFail($request->item_id)->increment('clicks');
            ResponseService::successResponse(null, 'Update Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> setItemTotalClick");
            ResponseService::errorResponse();
        }
    }

    public function getFeaturedSection(Request $request)
    {
        try {
            $featureSection = FeatureSection::orderBy('sequence', 'ASC');

            if (isset($request->slug)) {
                $featureSection->where('slug', $request->slug);
            }
            $featureSection = $featureSection->get();
            $tempRow = array();
            $rows = array();

            foreach ($featureSection as $row) {
                $items = Item::where('status', 'approved')->take(5)->with('user:id,name,email,mobile,profile,is_verified,show_personal_details,country_code', 'category:id,name,image', 'gallery_images:id,image,item_id', 'featured_items', 'favourites', 'item_custom_field_values.custom_field')->withCount('favourites')->has('user')->getNonExpiredItems();
                $items = match ($row->filter) {
                    "price_criteria" => $items->whereBetween('price', [$row->min_price, $row->max_price]),
                    "most_viewed" => $items->orderBy('clicks', 'DESC'),
                    "category_criteria" => (static function () use ($row, $items) {
                        $category = Category::whereIn('id', explode(',', $row->value))->with('children')->get();
                        $categoryIDS = HelperService::findAllCategoryIds($category);
                        return $items->whereIn('category_id', $categoryIDS)->orderBy('id', 'DESC');
                    })(),
                    "most_liked" => $items->orderBy('favourites_count', 'DESC'),
                };

                if (isset($request->city)) {
                    $items = $items->where('city', $request->city);
                }

                if (isset($request->state)) {
                    $items = $items->where('state', $request->state);
                }

                if (isset($request->country)) {
                    $items = $items->where('country', $request->country);
                }

                if (isset($request->area_id)) {
                    $items = $items->where('area_id', $request->area_id);
                }
                if (isset($request->latitude) &&  isset($request->longitude) && isset($request->radius)) {
                    $latitude = $request->latitude;
                    $longitude = $request->longitude;
                    $radius = $request->radius;

                    // Calculate distance using Haversine formula
                    $haversine = "(6371 * acos(cos(radians($latitude))
                                    * cos(radians(latitude))
                                    * cos(radians(longitude)
                                    - radians($longitude))
                                    + sin(radians($latitude))
                                    * sin(radians(latitude))))";

                    $items = $items->select('items.*')
                        ->selectRaw("{$haversine} AS distance")
                        ->withCount('favourites')
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->having('distance', '<', $radius)
                        ->orderBy('distance', 'asc');
                }
                if (Auth::check()) {
                    $items->with(['item_offers' => function ($q) {
                        $q->where('buyer_id', Auth::user()->id);
                    }, 'user_reports'           => function ($q) {
                        $q->where('user_id', Auth::user()->id);
                    }]);
                }
                $items = $items->get();
                //                $tempRow[$row->id]['section_id'] = $row->id;
                //                $tempRow[$row->id]['title'] = $row->title;
                //                $tempRow[$row->id]['style'] = $row->style;

                $tempRow[$row->id] = $row;
                $tempRow[$row->id]['total_data'] = count($items);
                if (count($items) > 0) {
                    $tempRow[$row->id]['section_data'] = new ItemCollection($items);
                } else {
                    $tempRow[$row->id]['section_data'] = [];
                }

                $rows[] = $tempRow[$row->id];
            }
            ResponseService::successResponse("Data Fetched Successfully", $rows);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getFeaturedSection");
            ResponseService::errorResponse();
        }
    }

    public function getPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required',
            // Removed payment_method and platform_type validation since we'll use cash by default
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            
            // Skip payment configuration check and use cash as default
            $paymentMethod = 'cash';
            
            $package = Package::whereNot('final_price', 0)->findOrFail($request->package_id);

            $purchasedPackage = UserPurchasedPackage::onlyActive()->where(['user_id' => Auth::user()->id, 'package_id' => $request->package_id])->first();
            if (!empty($purchasedPackage)) {
                ResponseService::errorResponse("You already have applied for this package");
            }
            
            // Use the payment method from request and generate a unique order ID
            $paymentGateway = $request->payment_method ?? 'cash';
            $uniqueOrderId = 'order_' . uniqid() . '_' . time();
            
            //Add Payment Data to Payment Transactions Table
            $paymentTransactionData = PaymentTransaction::create([
                'user_id'         => Auth::user()->id,
                'amount'          => $package->final_price,
                'payment_gateway' => $paymentGateway,
                'payment_status'  => 'pending',
                'order_id'        => $uniqueOrderId // Generate a unique payment reference
            ]);

            // Create a simple payment intent response for the client
            $paymentIntent = [
                'id' => $paymentTransactionData->order_id,
                'amount' => $package->final_price,
                'currency' => 'USD', // You may want to adjust this based on your default currency
                'status' => 'pending',
                'payment_method' => $paymentGateway
            ];

            // Create user purchased package record immediately since it's a  transaction
            UserPurchasedPackage::create([
                'user_id'                 => Auth::user()->id,
                'package_id'              => $request->package_id,
                'start_date'              => Carbon::now(),
                'end_date'                => $package->duration == "unlimited" ? null : Carbon::now()->addDays($package->duration),
                'total_limit'             => $package->item_limit == "unlimited" ? null : $package->item_limit,
                'used_limit'              => 0,
                'status'                  => 0,
                'payment_transactions_id' => $paymentTransactionData->id,
            ]);

            // Custom Array to Show as response
            $paymentGatewayDetails = array(
                ...$paymentIntent,
                'payment_transaction_id' => $paymentTransactionData->id,
            );

            DB::commit();
            ResponseService::successResponse("Package assigned successfully", ["payment_intent" => $paymentGatewayDetails, "payment_transaction" => $paymentTransactionData]);
        } catch (Throwable $e) {
            DB::rollBack();
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function getPaymentTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latest_only' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $paymentTransactions = PaymentTransaction::where('user_id', Auth::user()->id)->orderBy('id', 'DESC');
            if ($request->latest_only) {
                $paymentTransactions->where('created_at', '>', Carbon::now()->subMinutes(30)->toDateTimeString());
            }
            $paymentTransactions = $paymentTransactions->get();

            $paymentTransactions = collect($paymentTransactions)->map(function ($data) {
                if ($data->payment_status == "pending") {
                    try {
                        $paymentIntent = PaymentService::create($data->payment_gateway)->retrievePaymentIntent($data->order_id);
                    } catch (Throwable) {
                        //                        PaymentTransaction::find($data->id)->update(['payment_status' => "failed"]);
                    }

                    if (!empty($paymentIntent) && $paymentIntent['status'] != "pending") {
                        PaymentTransaction::find($data->id)->update(['payment_status' => $paymentIntent['status'] ?? "failed"]);
                    }
                }
                return $data;
            });

            ResponseService::successResponse("Payment Transactions Fetched", $paymentTransactions);
        } catch (Throwable $e) {
            ResponseService::logErrorResponse($e);
            ResponseService::errorResponse();
        }
    }

    public function createItemOffer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_id' => 'required|integer',
            // Make amount optional, will default to 0
            'amount'  => 'nullable|numeric',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::approved()->notOwner()->findOrFail($request->item_id);

            // Set default amount to 0 if not provided
            $amount = $request->has('amount') ? $request->amount : 0;

            $itemOffer = ItemOffer::updateOrCreate([
                'item_id'   => $request->item_id,
                'buyer_id'  => Auth::user()->id,
                'seller_id' => $item->user_id,
            ], ['amount' => $amount]);

            $itemOffer = $itemOffer->load('seller:id,name,profile', 'buyer:id,name,profile', 'item:id,name,description,price,image');

            $fcmMsg = [
                'user_id'           => $itemOffer->buyer->id,
                'user_name'         => $itemOffer->buyer->name,
                'user_profile'      => $itemOffer->buyer->profile,
                'user_type'         => 'Buyer',
                'item_id'           => $itemOffer->item->id,
                'item_name'         => $itemOffer->item->name,
                'item_image'        => $itemOffer->item->image,
                'item_price'        => $itemOffer->item->price,
                'item_offer_id'     => $itemOffer->id,
                'item_offer_amount' => $itemOffer->amount,
                // 'type'              => $notificationPayload['message_type'],
                // 'message_type_temp' => $notificationPayload['message_type']
            ];
            /* message_type is reserved keyword in FCM so removed here*/
            unset($fcmMsg['message_type']);

            // Always send notification regardless of amount
            $user_token = UserFcmToken::where('user_id', $item->user->id)->pluck('fcm_token')->toArray();
            $message = 'New chat request from buyer';
            NotificationService::sendFcmNotification($user_token, 'New Chat Request', $message, "offer", $fcmMsg);

            ResponseService::successResponse("Chat request sent successfully", $itemOffer);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> createItemOffer");
            ResponseService::errorResponse();
        }
    }

    public function getChatList(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:seller,buyer'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            //List of Blocked Users by Auth Users
            $authUserBlockList = BlockUser::where('user_id', Auth::user()->id)->pluck('blocked_user_id');


            $otherUserBlockList = BlockUser::where('blocked_user_id', Auth::user()->id)->pluck('user_id');
            $itemOffer = ItemOffer::with(['seller:id,name,profile', 'buyer:id,name,profile', 'item:id,name,description,price,image,status,deleted_at,sold_to', 'item.review' => function ($q) {
                $q->where('buyer_id', Auth::user()->id);
            }])->whereHas('buyer', function ($query) {
                $query->whereNull('deleted_at');
            })
                ->with(['chat' => function ($query) {
                    $query->latest('updated_at')->select('updated_at', 'item_offer_id', 'is_read', 'sender_id');
                }])->withCount([
                    'chat as unread_chat_count' => function ($query) {
                        $query->where('is_read', 0)
                            ->where('sender_id', '!=', Auth::user()->id);
                    }
                ])
                ->orderBy('id', 'DESC');
            if ($request->type == "seller") {
                $itemOffer = $itemOffer->where('seller_id', Auth::user()->id);
            } elseif ($request->type == "buyer") {
                $itemOffer = $itemOffer->where('buyer_id', Auth::user()->id);
            }
            $itemOffer = $itemOffer->paginate();


            $itemOffer->getCollection()->transform(function ($value) use ($request, $authUserBlockList, $otherUserBlockList) {
                // Your code here
                if ($request->type == "seller") {
                    $userBlocked = $authUserBlockList->contains($value->buyer_id) || $otherUserBlockList->contains($value->seller_id);
                } elseif ($request->type == "buyer") {
                    $userBlocked = $authUserBlockList->contains($value->seller_id) || $otherUserBlockList->contains($value->buyer_id);
                }
                $value->item->is_purchased = 0;
                if ($value->item->sold_to == Auth::user()->id) {
                    $value->item->is_purchased = 1;
                }
                $tempReview = $value->item->review;

                unset($value->item->review);
                $value->item->review = $tempReview[0] ?? null;
                $value->user_blocked = $userBlocked ?? false;
                return $value;
            });

            $itemOffer->getCollection()->transform(function ($offer) {
                $offer['last_message_time'] = $offer->chat->isNotEmpty() ? $offer->chat->first()->updated_at : null;
                return $offer;
            });

            ResponseService::successResponse("Chat List Fetched Successfully", $itemOffer);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getChatList");
            ResponseService::errorResponse();
        }
    }

    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_offer_id' => 'required|integer',
            'message'       => (!$request->file('file') && !$request->file('audio')) ? "required" : "nullable",
            'file'          => 'nullable|mimes:jpg,jpeg,png|max:6144',
            'audio'         => 'nullable|mimetypes:audio/mpeg,video/webm,audio/ogg,video/mp4,audio/x-wav,text/plain|max:6144',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            DB::beginTransaction();
            $user = Auth::user();
            //List of users that Auth user has blocked
            $authUserBlockList = BlockUser::where('user_id', $user->id)->get();

            //List of Other users that have blocked the Auth user
            $otherUserBlockList = BlockUser::where('blocked_user_id', $user->id)->get();

            $itemOffer = ItemOffer::with('item')->findOrFail($request->item_offer_id);
            if ($itemOffer->seller_id == $user->id) {
                //If Auth user is seller then check if buyer has blocked the user
                $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because You have blocked this user");
                }

                $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because other user has blocked you.");
                }
            } else {
                //If Auth user is seller then check if buyer has blocked the user
                $blockStatus = $authUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->buyer_id && $data->blocked_user_id == $itemOffer->seller_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because You have blocked this user");
                }

                $blockStatus = $otherUserBlockList->filter(function ($data) use ($itemOffer) {
                    return $data->user_id == $itemOffer->seller_id && $data->blocked_user_id == $itemOffer->buyer_id;
                });
                if (count($blockStatus) !== 0) {
                    ResponseService::errorResponse("You Cannot send message because other user has blocked you.");
                }
            }
            $chat = Chat::create([
                'sender_id'     => Auth::user()->id,
                'item_offer_id' => $request->item_offer_id,
                'message'       => $request->message,
                'file'          => $request->hasFile('file') ? FileService::compressAndUpload($request->file('file'), 'chat') : '',
                'audio'         => $request->hasFile('audio') ? FileService::compressAndUpload($request->file('audio'), 'chat') : '',
                'is_read'       => 0
            ]);

            if ($itemOffer->seller_id == $user->id) {
                $receiver_id = $itemOffer->buyer_id;
                $userType = "Seller";
            } else {
                $receiver_id = $itemOffer->seller_id;
                $userType = "Buyer";
            }
            $notificationPayload = $chat->toArray();

            $unreadMessagesCount = Chat::where('item_offer_id', $itemOffer->id)
                ->where('is_read', 0)
                ->count();

            $fcmMsg = [
                ...$notificationPayload,
                'user_id'           => $user->id,
                'user_name'         => $user->name,
                'user_profile'      => $user->profile,
                'user_type'         => $userType,
                'item_id'           => $itemOffer->item->id,
                'item_name'         => $itemOffer->item->name,
                'item_image'        => $itemOffer->item->image,
                'item_price'        => $itemOffer->item->price,
                'item_offer_id'     => $itemOffer->id,
                'item_offer_amount' => $itemOffer->amount,
                'type'              => $notificationPayload['message_type'],
                'message_type_temp' => $notificationPayload['message_type'],
                'unread_count'      => $unreadMessagesCount
            ];
            /* message_type is reserved keyword in FCM so removed here*/
            unset($fcmMsg['message_type']);
            $receiverFCMTokens = UserFcmToken::where('user_id', $receiver_id)->pluck('fcm_token')->toArray();
            $notification = NotificationService::sendFcmNotification($receiverFCMTokens, 'Message', $request->message, "chat", $fcmMsg);

            DB::commit();
            ResponseService::successResponse("Message Fetched Successfully", $chat, ['debug' => $notification]);
        } catch (Throwable $th) {
            Log::error('Exception in getItem: ' . $th->getMessage(), [
                'file' => $th->getFile(),
                'line' => $th->getLine(),
                'trace' => $th->getTraceAsString()
            ]);
            ResponseService::logErrorResponse($th, "API Controller -> getItem");
            ResponseService::errorResponse();
        }
    }

    public function getChatMessages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'item_offer_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $itemOffer = ItemOffer::owner()->findOrFail($request->item_offer_id);
            $chat = Chat::where('item_offer_id', $itemOffer->id)->orderBy('created_at', 'DESC')->paginate();
            $authUserId = auth::user()->id;
            Chat::where('item_offer_id', $itemOffer->id)
                ->where('sender_id', '!=', $authUserId)
                ->whereIn('id', $chat->pluck('id'))
                ->update(['is_read' => '1']);
            ResponseService::successResponse("Messages Fetched Successfully", $chat);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getChatMessages");
            ResponseService::errorResponse();
        }
    }

    public function deleteUser()
    {
        try {
            User::findOrFail(Auth::user()->id)->forceDelete();
            ResponseService::successResponse("User Deleted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> deleteUser");
            ResponseService::errorResponse();
        }
    }

    public function inAppPurchase(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase_token' => 'required',
            'payment_method' => 'required|in:google,apple',
            'package_id'     => 'required|integer'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $package = Package::findOrFail($request->package_id);
            $purchasedPackage = UserPurchasedPackage::where(['user_id' => Auth::user()->id, 'package_id' => $request->package_id])->first();
            if (!empty($purchasedPackage)) {
                ResponseService::errorResponse("You already have applied for this package");
            }

            $uniqueOrderId = 'order_' . uniqid() . '_' . time();

            PaymentTransaction::create([
                'user_id'         => Auth::user()->id,
                'amount'          => $package->final_price,
                'payment_gateway' => $request->payment_method,
                'order_id'        => $uniqueOrderId,
                'payment_status'  => 'success',
            ]);

            UserPurchasedPackage::create([
                'user_id'     => Auth::user()->id,
                'package_id'  => $request->package_id,
                'start_date'  => Carbon::now(),
                'total_limit' => $package->item_limit == "unlimited" ? null : $package->item_limit,
                'end_date'    => $package->duration == "unlimited" ? null : Carbon::now()->addDays($package->duration)
            ]);
            ResponseService::successResponse("Package Purchased Applied Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> inAppPurchase");
            ResponseService::errorResponse();
        }
    }

    public function blockUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            BlockUser::create([
                'user_id'         => Auth::user()->id,
                'blocked_user_id' => $request->blocked_user_id,
            ]);
            ResponseService::successResponse("User Blocked Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> blockUser");
            ResponseService::errorResponse();
        }
    }

    public function unblockUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'blocked_user_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            BlockUser::where([
                'user_id'         => Auth::user()->id,
                'blocked_user_id' => $request->blocked_user_id,
            ])->delete();
            ResponseService::successResponse("User Unblocked Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> unblockUser");
            ResponseService::errorResponse();
        }
    }

    public function getBlockedUsers()
    {
        try {
            $blockedUsers = BlockUser::where('user_id', Auth::user()->id)->pluck('blocked_user_id');
            $users = User::whereIn('id', $blockedUsers)->select(['id', 'name', 'profile'])->get();
            ResponseService::successResponse("User Unblocked Successfully", $users);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> unblockUser");
            ResponseService::errorResponse();
        }
    }

    public function getTips()
    {
        try {
            $tips = Tip::select(['id', 'description'])->orderBy('sequence', 'ASC')->with('translations')->get();
            ResponseService::successResponse("Tips Fetched Successfully", $tips);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getTips");
            ResponseService::errorResponse();
        }
    }

    public function getBlog(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category_id' => 'nullable|integer|exists:categories,id',
                'blog_id'     => 'nullable|integer|exists:blogs,id',
                'sort_by'     => 'nullable|in:new-to-old,old-to-new,popular',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $blogs = Blog::when(!empty($request->id), static function ($q) use ($request) {
                $q->where('id', $request->id);
                Blog::where('id', $request->id)->increment('views');
            })
                ->when(!empty($request->slug), function ($q) use ($request) {
                    $q->where('slug', $request->slug);
                    Blog::where('slug', $request->slug)->increment('views');
                })
                ->when(!empty($request->sort_by), function ($q) use ($request) {
                    if ($request->sort_by === 'new-to-old') {
                        $q->orderByDesc('created_at');
                    } elseif ($request->sort_by === 'old-to-new') {
                        $q->orderBy('created_at');
                    } else if ($request->sort_by === 'popular') {
                        $q->orderByDesc('views');
                    }
                })
                ->when(!empty($request->tag), function ($q) use ($request) {
                    $q->where('tags', 'like', "%" . $request->tag . "%");
                })->paginate();

            $otherBlogs = [];
            if (!empty($request->id) || !empty($request->slug)) {
                $otherBlogs = Blog::orderByDesc('id')->limit(3)->get();
            }
            // Return success response with the fetched blogs
            ResponseService::successResponse("Blogs fetched successfully", $blogs, ['other_blogs' => $otherBlogs]);
        } catch (Throwable $th) {
            // Log and handle exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getBlog');
            ResponseService::errorResponse("Failed to fetch blogs");
        }
    }

    public function getCountries(Request $request)
    {
        try {
            $searchQuery = $request->search ?? '';
            $countries = Country::withCount('states')->where('name', 'LIKE', "%{$searchQuery}%")->orderBy('name', 'ASC')->paginate();
            ResponseService::successResponse("Countries Fetched Successfully", $countries);
        } catch (Throwable $th) {
            // Log and handle any exceptions
            ResponseService::logErrorResponse($th, "API Controller -> getCountries");
            ResponseService::errorResponse("Failed to fetch countries");
        }
    }

    public function getStates(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'country_id' => 'nullable|integer',
            'search'     => 'nullable|string'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            $searchQuery = $request->search ?? '';
            $statesQuery = State::withCount('cities')
                ->where('name', 'LIKE', "%{$searchQuery}%")
                ->orderBy('name', 'ASC');

            if (isset($request->country_id)) {
                $statesQuery->where('country_id', $request->country_id);
            }

            $states = $statesQuery->paginate();

            ResponseService::successResponse("States Fetched Successfully", $states);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller->getStates");
            ResponseService::errorResponse("Failed to fetch states");
        }
    }

    public function getCities(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'state_id' => 'nullable|integer',
                'search'   => 'nullable|string'
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $searchQuery = $request->search ?? '';
            $citiesQuery = City::withCount('areas')
                ->where('name', 'LIKE', "%{$searchQuery}%")
                ->orderBy('name', 'ASC');

            if (isset($request->state_id)) {
                $citiesQuery->where('state_id', $request->state_id);
            }

            $cities = $citiesQuery->paginate();

            ResponseService::successResponse("Cities Fetched Successfully", $cities);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller->getCities");
            ResponseService::errorResponse("Failed to fetch cities");
        }
    }

    public function getAreas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'city_id' => 'nullable|integer',
            'search'  => 'nullable'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $searchQuery = $request->search ?? '';
            $data = Area::search($searchQuery)->orderBy('name', 'ASC');
            if (isset($request->city_id)) {
                $data->where('city_id', $request->city_id);
            }

            $data = $data->paginate();
            ResponseService::successResponse("Area fetched Successfully", $data);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> getAreas');
            ResponseService::errorResponse();
        }
    }

    public function getFaqs()
    {
        try {
            $faqs = Faq::get();
            ResponseService::successResponse("FAQ Data fetched Successfully", $faqs);
        } catch (Throwable $th) {
            // Log and handle exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getFaqs');
            ResponseService::errorResponse("Failed to fetch Faqs");
        }
    }

    public function getAllBlogTags()
    {
        try {
            $tagsArray = [];
            Blog::select('tags')->chunk(100, function ($blogs) use (&$tagsArray) {
                foreach ($blogs as $blog) {
                    foreach ($blog->tags as $tags) {
                        $tagsArray[] = $tags;
                    }
                }
            });
            $tagsArray = array_unique($tagsArray);
            ResponseService::successResponse("Blog Tags Successfully", $tagsArray);
        } catch (Throwable $th) {
            // Log and handle exceptions
            ResponseService::logErrorResponse($th, 'API Controller -> getAllBlogTags');
            ResponseService::errorResponse("Failed to fetch Tags");
        }
    }

    public function storeContactUs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'    => 'required',
            'email'   => 'required|email',
            'subject' => 'required',
            'message' => 'required'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            ContactUs::create($request->all());
            ResponseService::successResponse("Contact Us Stored Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> storeContactUs');
            ResponseService::errorResponse();
        }
    }

    public function addItemReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'review'  => 'nullable|string',
            'ratings' => 'required|numeric|between:0,5',
            'item_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $item = Item::with('user')->notOwner()->findOrFail($request->item_id);
            if ($item->sold_to !== Auth::id()) {
                ResponseService::errorResponse("You can only review items that you have purchased.");
            }
            if ($item->status !== 'sold out') {
                ResponseService::errorResponse("The item must be marked as 'sold out' before you can review it.");
            }
            $existingReview = SellerRating::where('item_id', $request->item_id)->where('buyer_id', Auth::id())->first();
            if ($existingReview) {
                ResponseService::errorResponse("You have already reviewed this item.");
            }
            $review = SellerRating::create([
                'item_id'   => $request->item_id,
                'buyer_id'  => Auth::user()->id,
                'seller_id' => $item->user_id,
                'ratings'   => $request->ratings,
                'review'    => $request->review ?? '',
            ]);

            ResponseService::successResponse("Your review has been submitted successfully.", $review);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> storeContactUs');
            ResponseService::errorResponse();
        }
    }

    public function getSeller(Request $request)
    {
        $request->validate([
            'id' => 'required|integer'
        ]);

        try {
            // Fetch seller by ID
            $seller = User::findOrFail($request->id);

            // Fetch seller ratings
            $ratings = SellerRating::where('seller_id', $seller->id)->with('buyer:id,name,profile')->paginate(10);
            $averageRating = $ratings->avg('ratings');

            // Response structure
            $response = [
                'seller'  => [
                    ...$seller->toArray(),
                    'average_rating' => $averageRating,
                ],
                'ratings' => $ratings,
            ];

            // Send success response
            ResponseService::successResponse("Seller Details Fetched Successfully", $response);
        } catch (Throwable $th) {
            // Log and handle error response
            ResponseService::logErrorResponse($th, "API Controller -> getSeller");
            ResponseService::errorResponse();
        }
    }


    public function renewItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'item_id'    => 'required|exists:items,id',
                'package_id' => 'required|exists:packages,id',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }

            $user = Auth::user();
            $item = Item::findOrFail($request->item_id);
            $rawStatus = $item->getAttributes()['status'];
            $package = Package::where('id', $request->package_id)->firstOrFail();
            $userPackage = UserPurchasedPackage::onlyActive()->where([
                'user_id'    => $user->id,
                'package_id' => $package->id
            ])->first();

            if (!$userPackage) {
                ResponseService::errorResponse("You have not purchased this package");
            }

            $currentDate = Carbon::now();
            if (Carbon::parse($item->expiry_date)->gt($currentDate)) {
                ResponseService::errorResponse("Item has not expired yet, so it cannot be renewed");
            }

            $expiryDays = (int)$package->duration;
            $newExpiryDate = $currentDate->copy()->addDays($expiryDays);
            if ($package->duration == 'unlimited') {
                $item->expiry_date = null;
            } else {
                $item->expiry_date = $newExpiryDate;
            }
            $item->status = $rawStatus;
            $item->save();

            ++$userPackage->used_limit;
            $userPackage->save();
            ResponseService::successResponse("Item renewed successfully", $item);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> renewItem");
            ResponseService::errorResponse();
        }
    }

    public function getMyReview(Request $request)
    {
        try {
            $ratings = SellerRating::where('seller_id', Auth::user()->id)->with('seller:id,name,profile', 'buyer:id,name,profile', 'item:id,name,price,image,description')->paginate(10);
            $averageRating = $ratings->avg('ratings');
            $response = [
                'average_rating' => $averageRating,
                'ratings'        => $ratings,
            ];

            ResponseService::successResponse("Seller Details Fetched Successfully", $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getSeller");
            ResponseService::errorResponse();
        }
    }

    public function addReviewReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'report_reason'    => 'required|string',
            'seller_review_id' => 'required',
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            $ratings = SellerRating::where('seller_id', Auth::user()->id)->findOrFail($request->seller_review_id);
            $ratings->update([
                'report_status' => 'reported',
                'report_reason' => $request->report_reason
            ]);

            ResponseService::successResponse("Your report has been submitted successfully.", $ratings);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, 'API Controller -> addReviewReport');
            ResponseService::errorResponse();
        }
    }


    public function getVerificationFields()
    {
        try {
            $fields = VerificationField::all();
            ResponseService::successResponse("Verification Field Fetched Successfully", $fields);
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "API Controller -> addVerificationFieldValues");
            ResponseService::errorResponse();
        }
    }

    public function sendVerificationRequest(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'verification_field'         => 'sometimes|array',
                'verification_field.*'       => 'sometimes',
                'verification_field_files'   => 'nullable|array',
                'verification_field_files.*' => 'nullable|mimes:jpeg,png,jpg,pdf,doc|max:6144',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            DB::beginTransaction();

            $user = Auth::user();
            $verificationRequest = VerificationRequest::updateOrCreate([
                'user_id' => $user->id,
            ], ['status' => 'pending']);

            $user = auth()->user();
            if ($request->verification_field) {
                $itemCustomFieldValues = [];
                foreach ($request->verification_field as $id => $value) {
                    $itemCustomFieldValues[] = [
                        'user_id'                 => $user->id,
                        'verification_field_id'   => $id,
                        'verification_request_id' => $verificationRequest->id,
                        'value'                   => $value,
                        'created_at'              => now(),
                        'updated_at'              => now()
                    ];
                }
                if (count($itemCustomFieldValues) > 0) {
                    VerificationFieldValue::upsert($itemCustomFieldValues, ['user_id', 'verification_fields_id'], ['value', 'updated_at']);
                }
            }

            if ($request->verification_field_files) {
                $itemCustomFieldValues = [];
                foreach ($request->verification_field_files as $fieldId => $file) {
                    $itemCustomFieldValues[] = [
                        'user_id'                 => $user->id,
                        'verification_field_id'   => $fieldId,
                        'verification_request_id' => $verificationRequest->id,
                        'value'                   => !empty($file) ? FileService::upload($file, 'verification_field_files') : '',
                        'created_at'              => now(),
                        'updated_at'              => now()
                    ];
                }
                if (count($itemCustomFieldValues) > 0) {
                    VerificationFieldValue::upsert($itemCustomFieldValues, ['user_id', 'verification_field_id'], ['value', 'updated_at']);
                }
            }
            DB::commit();

            ResponseService::successResponse("Verification request submitted successfully.");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> SendVerificationRequest");
            ResponseService::errorResponse();
        }
    }


    public function getVerificationRequest(Request $request)
    {
        try {
            $verificationRequest = VerificationRequest::with('verification_field_values')->owner()->first();

            if (empty($verificationRequest)) {
                ResponseService::errorResponse("No Request found");
            }
            $response = $verificationRequest->toArray();
            $response['verification_fields'] = [];

            foreach ($verificationRequest->verification_field_values as $key2 => $verificationFieldValue) {
                $tempRow = [];

                if ($verificationFieldValue->relationLoaded('verification_field')) {

                    if (!empty($verificationFieldValue->verification_field)) {

                        $tempRow = $verificationFieldValue->verification_field->toArray();

                        if ($verificationFieldValue->verification_field->type == "fileinput") {
                            if (!is_array($verificationFieldValue->value)) {
                                $tempRow['value'] = !empty($verificationFieldValue->value) ? [url(Storage::url($verificationFieldValue->value))] : [];
                            } else {
                                $tempRow['value'] = null;
                            }
                        } else {
                            $tempRow['value'] = $verificationFieldValue->value ?? [];
                        }

                        // $tempRow['verification_field_value'] = !empty($verificationFieldValue) ? $verificationFieldValue->toArray() : (object)[];
                        if (!empty($verificationFieldValue)) {
                            $tempRow['verification_field_value'] = $verificationFieldValue->toArray();

                            if ($verificationFieldValue->verification_field->type == "fileinput") {

                                $tempRow['verification_field_value'][]['value'] = !empty($verificationFieldValue->value) ? [url(Storage::url($verificationFieldValue->value))] : [];
                            } else {
                                $tempRow['verification_field_value']['value'] = $verificationFieldValue->value ?? [];
                            }
                        } else {
                            $tempRow['verification_field_value'] = (object)[];
                        }
                    }
                    unset($tempRow['verification_field_value']['verification_field']);
                    $response['verification_field'][] = $tempRow;
                }
            }
            ResponseService::successResponse("Verification request fetched successfully.", $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> SendVerificationRequest");
            ResponseService::errorResponse();
        }
    }
    public function seoSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'page' => 'nullable',
            ]);

            if ($validator->fails()) {
                ResponseService::validationError($validator->errors()->first());
            }
            $settings = new SeoSetting();
            if (!empty($request->page)) {
                $settings = $settings->where('page', $request->page);
            }

            $settings = $settings->get();
            ResponseService::successResponse("SEO settings fetched successfully.", $settings);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> seoSettings");
            ResponseService::errorResponse();
        }
    }

    /**
     * Add review for a user/expert/business
     */
    public function addUserReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'ratings' => 'required|numeric|between:1,5',
            'review'  => 'required|string',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            // Check if the user is trying to review themselves
            if ($request->user_id == Auth::id()) {
                ResponseService::errorResponse("You cannot review yourself.");
            }

            // Check if the user has already reviewed this user
            $existingReview = UserReview::where('user_id', $request->user_id)
                                        ->where('reviewer_id', Auth::id())
                                        ->first();
            
            if ($existingReview) {
                ResponseService::errorResponse("You have already reviewed this user.");
            }

            // Create the review
            $review = UserReview::create([
                'user_id'     => $request->user_id,
                'reviewer_id' => Auth::id(),
                'ratings'     => $request->ratings,
                'review'      => $request->review,
            ]);

            ResponseService::successResponse("Review submitted successfully.", $review);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> addUserReview");
            ResponseService::errorResponse();
        }
    }

    /**
     * Add review for a service/experience
     */
    public function addServiceReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer|exists:items,id',
            'user_id'    => 'required|integer|exists:users,id',
            'ratings'    => 'required|numeric|between:1,5',
            'review'     => 'required|string',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            // Verify the item exists and belongs to the specified user
            $item = Item::findOrFail($request->service_id);
            
            if ($item->user_id != $request->user_id) {
                ResponseService::errorResponse("The specified service does not belong to the specified user.");
            }

            // Check if the user is trying to review their own service
            if ($request->user_id == Auth::id()) {
                ResponseService::errorResponse("You cannot review your own service.");
            }

            // Check if the user has already reviewed this service
            $existingReview = ServiceReview::where('service_id', $request->service_id)
                                           ->where('reviewer_id', Auth::id())
                                           ->first();
            
            if ($existingReview) {
                ResponseService::errorResponse("You have already reviewed this service.");
            }

            // Create the review
            $review = ServiceReview::create([
                'service_id'  => $request->service_id,
                'user_id'     => $request->user_id,
                'reviewer_id' => Auth::id(),
                'ratings'     => $request->ratings,
                'review'      => $request->review,
            ]);

            ResponseService::successResponse("Review submitted successfully.", $review);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> addServiceReview");
            ResponseService::errorResponse();
        }
    }

    /**
     * Get reviews for a specific item/service
     */
    public function getItemReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer|exists:items,id',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            // Get all reviews for the specified item
            $reviews = ServiceReview::where('service_id', $request->service_id)
                                    ->with(['reviewer:id,name,profile'])
                                    ->paginate(10);
            
            // Calculate average rating
            $averageRating = $reviews->avg('ratings');

            $response = [
                'reviews' => $reviews,
                'average_rating' => $averageRating,
            ];

            Log::info($response);

            ResponseService::successResponse("Reviews fetched successfully.", $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getItemReview");
            ResponseService::errorResponse();
        }
    }

    /**
     * Get reviews for a specific user profile
     */
    public function getUserReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }

        try {
            // Get all reviews for the specified user
            $reviews = UserReview::where('user_id', $request->user_id)
                                 ->with(['reviewer:id,name,profile'])
                                 ->paginate(10);
            
            // Calculate average rating
            $averageRating = $reviews->avg('ratings');

            $response = [
                'reviews' => $reviews,
                'average_rating' => $averageRating,
            ];

            ResponseService::successResponse("Success", $response);
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "API Controller -> getUserReview");
            ResponseService::errorResponse();
        }
    }

    /**
     * Process special tags for display
     */
    private function determineSpecialTags($item)
    {
        Log::info("Processing special_tags for item {$item->id}");
        
        // Return empty object if no special tags
        if (!isset($item->special_tags) || empty($item->special_tags)) {
            Log::info("No special_tags found for item {$item->id}, returning empty array");
            return [];
        }
        
        // Process special_tags value based on its type
        try {
            // If it's already an object or array
            if (is_object($item->special_tags) || is_array($item->special_tags)) {
                Log::info("Item {$item->id} special_tags is already an object/array");
                $tags = (array)$item->special_tags;
            } 
            // If it's a string, try to decode it
            else if (is_string($item->special_tags)) {
                Log::info("Decoding JSON string for item {$item->id}: " . $item->special_tags);
                // Try to decode as JSON
                $decodedTags = json_decode($item->special_tags, true);
                
                // If decoding failed, try again by replacing escaped quotes
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $cleaned = str_replace('\"', '"', $item->special_tags);
                    $decodedTags = json_decode($cleaned, true);
                }
                
                $tags = $decodedTags ?: [];
            } else {
                // Unknown type
                Log::info("Special tags has unknown type: " . gettype($item->special_tags));
                $tags = [];
            }
            
            // Log the result
            Log::info("Processed special_tags for item {$item->id}: ", ['tags' => $tags]);
            
            return $tags;
        } catch (\Exception $e) {
            Log::error("Error processing special_tags for item {$item->id}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Determine the service type (service or exclusive experience)
     */
    private function determineServiceType($item)
    {
        // Check provider_item_type field
        if (isset($item->provider_item_type) && $item->provider_item_type === 'experience') {
            return 'exclusive_experience';
        }
        
        // Default to regular service if no explicit type is found
        return 'service';
    }

    /**
     * Determine the user type (expert or business)
     */
    private function determineUserType($user)
    {
        if (!$user) {
            return null;
        }
        
        // Check if user has business profile or expert designation
        if (isset($user->account_type) && $user->account_type === 'business') {
            return 'business';
        } elseif (isset($user->is_expert) && $user->is_expert) {
            return 'expert';
        }
        
        return 'regular';
    }
}
            