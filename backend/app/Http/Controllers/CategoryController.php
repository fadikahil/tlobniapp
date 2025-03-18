<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryTranslation;
use App\Models\CustomField;
use App\Models\CustomFieldCategory;
use App\Services\BootstrapTableService;
use App\Services\CachingService;
use App\Services\FileService;
use App\Services\HelperService;
use App\Services\ResponseService;
use DB;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;
use function compact;
use function view;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller {
    private string $uploadFolder;

    public function __construct() {
        $this->uploadFolder = "category";
    }

    public function index(Request $request) {
        ResponseService::noAnyPermissionThenRedirect(['category-list', 'category-create', 'category-update', 'category-delete']);
        $type = $request->input('type', 'service_experience');
        
        // Redirect to the appropriate type-specific page
        if ($type == 'providers') {
            return redirect()->route('category.providers');
        } else {
            return redirect()->route('category.service.experience');
        }
    }

    /**
     * Display Service & Experience Categories
     */
    public function serviceExperienceCategories() {
        ResponseService::noAnyPermissionThenRedirect(['category-list', 'category-create', 'category-update', 'category-delete']);
        return view('category.service_experience_categories');
    }
    
    /**
     * Display Provider Categories
     */
    public function providerCategories() {
        ResponseService::noAnyPermissionThenRedirect(['category-list', 'category-create', 'category-update', 'category-delete']);
        return view('category.provider_categories');
    }

    public function create(Request $request) {
        ResponseService::noPermissionThenRedirect('category-create');
        $languages = CachingService::getLanguages()->where('code', '!=', 'en')->values();
        $type = $request->input('type', 'service_experience');
        
        // Load initial parent categories
        $parentCategories = [];
        try {
            // Get all categories of the selected type
            $categories = Category::where('type', $type)
                ->orderBy('sequence')
                ->get();
            
            // Build a hierarchical structure with level information
            $result = [];
            $this->buildCategoryHierarchy($categories, $result);
            $parentCategories = $result;
            
        } catch (Throwable $th) {
            Log::error("Error loading parent categories: " . $th->getMessage());
        }
        
        return view('category.create', compact('languages', 'type', 'parentCategories'));
    }

    public function store(Request $request) {
        ResponseService::noPermissionThenSendJson('category-create');
        $request->validate([
            'name'               => 'required',
            'image'              => 'nullable|mimes:jpg,jpeg,png|max:6144',
            'parent_category_id' => 'nullable|integer',
            'description'        => 'nullable',
            'slug'               => 'required',
            'status'             => 'required|boolean',
            'translations'       => 'nullable|array',
            'translations.*'     => 'nullable|string',
            'type'               => 'required|in:service_experience,providers',
        ]);

        try {
            $data = $request->all();
            $data['slug'] = HelperService::generateUniqueSlug(new Category(), $request->slug);

            if ($request->hasFile('image')) {
                $data['image'] = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);
            }

            $category = Category::create($data);

            if (!empty($request->translations)) {
                foreach ($request->translations as $key => $value) {
                    if (!empty($value)) {
                        $category->translations()->create([
                            'name'        => $value,
                            'language_id' => $key,
                        ]);
                    }
                }
            }

            ResponseService::successRedirectResponse("Category Added Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorRedirect($th);
            ResponseService::errorRedirectResponse();
        }
    }


    public function show(Request $request, $category) {
        // For debugging
        Log::info('Category show method called', [
            'category' => $category,
            'type' => $request->input('type'),
            'all_params' => $request->all()
        ]);
        
        ResponseService::noPermissionThenSendJson('category-list');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'sequence');
        $order = $request->input('order', 'ASC');
        $type = $request->input('type', 'service_experience');
        
        $sql = Category::withCount('subcategories')
            ->orderBy($sort, $order)
            ->withCount('custom_fields')
            ->with('subcategories')
            ->where('type', $type);
            
        if ($category == "0") {
            $sql->whereNull('parent_category_id');
        } else {
            $sql->where('parent_category_id', $category);
        }
        if (!empty($request->search)) {
            $sql = $sql->search($request->search);
        }
        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        
        // For debugging
        Log::info('Category query results', [
            'sql' => $sql->toSql(),
            'bindings' => $sql->getBindings(),
            'count' => $total,
            'result_count' => $result->count()
        ]);
        
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $no = 1;

        foreach ($result as $key => $row) {
            $operate = '';
            // Check if user has category-update permission
            try {
                ResponseService::noPermissionThenSendJson('category-update');
                $operate .= BootstrapTableService::editButton(route('category.edit', $row->id));
            } catch (\Exception $e) {
                // User doesn't have permission, do nothing
            }

            // Check if user has category-delete permission
            try {
                ResponseService::noPermissionThenSendJson('category-delete');
                $operate .= BootstrapTableService::ajaxDeleteButton(route('category.destroy', $row->id), $row->id);
            } catch (\Exception $e) {
                // User doesn't have permission, do nothing
            }
            
            if ($row->subcategories_count > 1) {
                $operate .= BootstrapTableService::button('fa fa-list-ol',route('sub.category.order.change', $row->id),['btn-secondary']);
            }
            $tempRow = $row->toArray();
            $tempRow['no'] = $no++;
            $tempRow['operate'] = $operate;
            $tempRow['items_count'] = $row->all_items_count;
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function edit($id) {
        ResponseService::noPermissionThenRedirect('category-update');
        $category_data = Category::findOrFail($id);
        // Fetch translations for the category
        $translations = $category_data->translations->pluck('name', 'language_id')->toArray();
        $languages = CachingService::getLanguages()->where('code', '!=', 'en')->values();
        
        // Load parent categories
        $parentCategories = [];
        try {
            // Get all categories of the selected type
            $categories = Category::where('type', $category_data->type)
                ->where('id', '!=', $id) // Exclude current category to prevent self-reference
                ->orderBy('sequence')
                ->get();
            
            // Build a hierarchical structure with level information
            $result = [];
            $this->buildCategoryHierarchy($categories, $result);
            $parentCategories = $result;
            
        } catch (Throwable $th) {
            Log::error("Error loading parent categories: " . $th->getMessage());
        }
        
        return view('category.edit', compact('category_data', 'languages', 'translations', 'parentCategories'));
    }

    public function update(Request $request, $id) {
        ResponseService::noPermissionThenRedirect('category-update');
        
        $request->validate([
            'name'               => 'required',
            'image'              => 'nullable|mimes:jpg,jpeg,png|max:6144',
            'parent_category_id' => 'nullable|integer',
            'description'        => 'nullable',
            'slug'               => 'required',
            'status'             => 'required|boolean',
            'translations'       => 'nullable|array',
            'translations.*'     => 'nullable|string',
        ]);

        try {
            $category = Category::find($id);
            
            $data = $request->all();
            $data['slug'] = HelperService::generateUniqueSlug(new Category(), $request->slug, $id);
            
            if ($request->hasFile('image')) {
                if ($category->image && file_exists(public_path('uploads/' . $category->image))) {
                    @unlink(public_path('uploads/' . $category->image));
                }
                
                $data['image'] = FileService::compressAndUpload($request->file('image'), $this->uploadFolder);
            }
            
            $category->update($data);
            
            CategoryTranslation::where(['category_id' => $id])->delete();
            
            if (!empty($request->translations)) {
                foreach ($request->translations as $key => $value) {
                    if (!empty($value)) {
                        $category->translations()->create([
                            'name'        => $value,
                            'language_id' => $key,
                        ]);
                    }
                }
            }
            
            ResponseService::successRedirectResponse("Category Updated Successfully", route('category.index', ['type' => $category->type]));
        } catch (QueryException $e) {
            ResponseService::errorRedirectResponse("Unable to update, there is an issue with database operation");
        } catch (Throwable $e) {
            ResponseService::errorRedirectResponse($e->getMessage());
        }
    }

    public function destroy($id) {
        ResponseService::noPermissionThenSendJson('category-delete');
        try {
            $category = Category::find($id);
            if (!$category) {
                return response()->json([
                    'success' => false,
                    'message' => 'Category not found'
                ]);
            }
            
            if ($category->items_count > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category. It has associated items.'
                ]);
            }
            
            if ($category->delete()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Category deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category'
            ]);
            
        } catch (QueryException $th) {
            Log::error("CategoryController -> delete: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category. Remove associated subcategories and custom fields first.'
            ]);
        } catch (Throwable $th) {
            Log::error("CategoryController -> delete: " . $th->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $th->getMessage()
            ]);
        }
    }

    public function getSubCategories($id) {
        ResponseService::noPermissionThenRedirect('category-list');
        $subcategories = Category::where('parent_category_id', $id)
            ->with('subcategories')
            ->withCount('custom_fields')
            ->withCount('subcategories')
            ->withCount('items')
            ->orderBy('sequence')
            ->get()
            ->map(function ($subcategory) {
                $operate = '';
                // Check if user has category-update permission
                try {
                    ResponseService::noPermissionThenSendJson('category-update');
                    $operate .= BootstrapTableService::editButton(route('category.edit', $subcategory->id));
                } catch (\Exception $e) {
                    // User doesn't have permission, do nothing
                }

                // Check if user has category-delete permission
                try {
                    ResponseService::noPermissionThenSendJson('category-delete');
                    $operate .= BootstrapTableService::deleteButton(route('category.destroy', $subcategory->id));
                } catch (\Exception $e) {
                    // User doesn't have permission, do nothing
                }
                
                if ($subcategory->subcategories_count > 1) {
                    $operate .= BootstrapTableService::button('fa fa-list-ol',route('sub.category.order.change',$subcategory->id),['btn-secondary']);
                }
                $subcategory->operate = $operate;
                return $subcategory;
            });

        return response()->json($subcategories);
    }

    public function customFields($id) {
        ResponseService::noPermissionThenRedirect('custom-field-list');
        $category = Category::find($id);
        $p_id = $category->parent_category_id;
        $cat_id = $category->id;
        $category_name = $category->name;

        return view('category.custom-fields', compact('cat_id', 'category_name', 'p_id'));
    }

    public function getCategoryCustomFields(Request $request, $id) {
        ResponseService::noPermissionThenSendJson('custom-field-list');
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 10);
        $sort = $request->input('sort', 'id');
        $order = $request->input('order', 'ASC');

        $sql = CustomField::whereHas('categories', static function ($q) use ($id) {
            $q->where('category_id', $id);
        })->orderBy($sort, $order);

        if (isset($request->search)) {
            $sql->search($request->search);
        }

        $sql->take($limit);
        $total = $sql->count();
        $res = $sql->skip($offset)->take($limit)->get();
        $bulkData = array();
        $rows = array();
        $tempRow['type'] = '';


        foreach ($res as $row) {
            $tempRow = $row->toArray();
//            $operate = BootstrapTableService::editButton(route('custom-fields.edit', $row->id));
            $operate = BootstrapTableService::deleteButton(route('category.custom-fields.destroy', [$id, $row->id]));
            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        $bulkData['total'] = $total;
        return response()->json($bulkData);
    }

    public function destroyCategoryCustomField($categoryID, $customFieldID) {
        try {
            ResponseService::noPermissionThenRedirect('custom-field-delete');
            CustomFieldCategory::where(['category_id' => $categoryID, 'custom_field_id' => $customFieldID])->delete();
            ResponseService::successResponse("Custom Field Deleted Successfully");
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "CategoryController -> destroyCategoryCustomField");
            ResponseService::errorResponse('Something Went Wrong');
        }

    }

    public function categoriesReOrder(Request $request) {
        ResponseService::noPermissionThenRedirect('category-update');
        $type = $request->input('type', 'service_experience');
        return view('category.categories-order', compact('type'));
    }

    public function subCategoriesReOrder(Request $request ,$id) {
        $categories = Category::with('subcategories')->where('parent_category_id', $id)->orderBy('sequence')->get();
        return view('category.sub-categories-order', compact('categories'));
    }

    public function updateOrder(Request $request) {
        ResponseService::noPermissionThenSendJson('category-update');
        $ids = $request->input('ids');
        $type = $request->input('type', 'service_experience');
        
        try {
            if (!empty($ids)) {
                for ($i = 0; $i < count($ids); $i++) {
                    Category::where(['id' => $ids[$i], 'type' => $type])->update(['sequence' => $i + 1]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Get parent categories based on type
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getParentCategories(Request $request)
    {
        try {
            $type = $request->input('type', 'service_experience');
            
            // Get all categories of the selected type
            $categories = Category::where('type', $type)
                ->orderBy('sequence')
                ->get();
            
            // Build a hierarchical structure with level information
            $result = [];
            $this->buildCategoryHierarchy($categories, $result);
            
            return response()->json([
                'success' => true,
                'categories' => $result,
                'count' => count($result),
                'type' => $type
            ]);
        } catch (Throwable $th) {
            Log::error("CategoryController -> getParentCategories: " . $th->getMessage() . ' --> ' . $th->getFile() . ' At Line : ' . $th->getLine());
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $th->getMessage(),
                'error' => $th->getMessage()
            ]);
        }
    }
    
    /**
     * Build a hierarchical structure of categories with level information
     * 
     * @param \Illuminate\Database\Eloquent\Collection $categories
     * @param array $result
     * @param int|null $parentId
     * @param int $level
     * @return void
     */
    private function buildCategoryHierarchy($categories, &$result, $parentId = null, $level = 0)
    {
        $filteredCategories = $categories->filter(function ($category) use ($parentId) {
            return $category->parent_category_id == $parentId;
        });
        
        foreach ($filteredCategories as $category) {
            $categoryData = $category->toArray();
            $categoryData['level'] = $level;
            $result[] = $categoryData;
            
            // Process children
            $this->buildCategoryHierarchy($categories, $result, $category->id, $level + 1);
        }
    }
}
