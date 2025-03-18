<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\BootstrapTableService;
use App\Services\ResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;
use Throwable;

class StaffController extends Controller {

    public function index() {
        ResponseService::noAnyPermissionThenRedirect(['staff-list', 'staff-create', 'staff-update', 'staff-delete']);
        $roles = Role::whereIn('name', ['Super Admin', 'Staff'])->get();
        return view('staff.index', compact('roles'));
    }

    public function create() {
        ResponseService::noPermissionThenRedirect('staff-create');
        $roles = Role::whereIn('name', ['Super Admin', 'Staff'])->get();
        return view('staff.create', compact('roles'));
    }

    public function store(Request $request) {
        ResponseService::noPermissionThenRedirect('staff-create');
        $validator = Validator::make($request->all(), [
            'name'     => 'required',
            'email'    => 'required|email|unique:users',
            'password' => 'required',
            'role'     => 'required'
        ]);

        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        
        // Validate that the role is either Super Admin or Staff
        $role = Role::find($request->role);
        if (!$role || !in_array($role->name, ['Super Admin', 'Staff'])) {
            ResponseService::validationError('Invalid role selected');
        }
        
        try {
            DB::beginTransaction();
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password)
            ]);

            $user->syncRoles($request->role);
            DB::commit();
            ResponseService::successResponse('User created Successfully');
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "StaffController --> store");
            ResponseService::errorResponse();
        }
    }


    public function update(Request $request, $id) {
        ResponseService::noPermissionThenRedirect('staff-edit');
        $validator = Validator::make($request->all(), [
            'name'    => 'required',
            'email'   => 'required|email|unique:users,email,' . $id,
            'role_id' => 'required'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        
        // Validate that the role is either Super Admin or Staff
        $role = Role::find($request->role_id);
        if (!$role || !in_array($role->name, ['Super Admin', 'Staff'])) {
            ResponseService::validationError('Invalid role selected');
        }
        
        try {
            DB::beginTransaction();
            $user = User::withTrashed()->with('roles')->findOrFail($id);
            
            // Prevent updating own role
            if (Auth::id() == $id) {
                ResponseService::validationError('You cannot change your own role');
            }
            
            // Prevent updating Super Admin users
            if ($user->roles->first()->name === 'Super Admin' && $role->name !== 'Super Admin') {
                ResponseService::validationError('Cannot change role of Super Admin user');
            }
            
            $user->update([
                ...$request->all()
            ]);

            $oldRole = $user->roles;
            if ($oldRole[0]->id !== $request->role_id) {
                $newRole = Role::findById($request->role_id);
                $user->removeRole($oldRole[0]);
                $user->assignRole($newRole);
            }

            DB::commit();
            ResponseService::successResponse('User Update Successfully');
        } catch (Throwable $th) {
            DB::rollBack();
            ResponseService::logErrorResponse($th, "StaffController --> update");
            ResponseService::errorResponse();
        }
    }

    public function show(Request $request) {
        ResponseService::noPermissionThenRedirect('staff-list');
        $offset = $request->offset ?? 0;
        $limit = $request->limit ?? 10;
        $sort = $request->sort ?? 'id';
        $order = $request->order ?? 'DESC';

        // Query users with Super Admin or Staff roles
        $sql = User::withTrashed()->with('roles')->orderBy($sort, $order)->whereHas('roles', function ($q) {
            $q->whereIn('name', ['Super Admin', 'Staff']);
        });

        if (!empty($request->search)) {
            $sql->search($request->search);
        }
        $total = $sql->count();
        $sql->skip($offset)->take($limit);
        $result = $sql->get();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        
        // Get current user ID
        $currentUserId = Auth::id();
        
        foreach ($result as $key => $row) {
            $operate = '';
            $tempRow = $row->toArray();
            
            // Set role name
            $tempRow['role_name'] = $row->roles[0]->name ?? '';
            
            // Set status - but mark current user's status as non-editable
            $tempRow['status'] = empty($row->deleted_at);
            $tempRow['status_editable'] = ($row->id !== $currentUserId);
            
            // Only allow editing/deleting Staff users, not Super Admin users
            // Also prevent editing/deleting the current user
            if (!($row->roles[0]->name === 'Super Admin') && ($row->id !== $currentUserId)) {
                try {
                    ResponseService::noPermissionThenRedirect('staff-update', false);
                    $operate .= BootstrapTableService::editButton(route('staff.update', $row->id), true);
                    $operate .= BootstrapTableService::editButton(route('staff.change-password', $row->id), true, '#resetPasswordModel', null, $row->id, 'bi bi-key');
                } catch (\Exception $e) {
                    // User doesn't have update permission
                }

                try {
                    ResponseService::noPermissionThenRedirect('staff-delete', false);
                    $operate .= BootstrapTableService::deleteButton(route('staff.destroy', $row->id));
                } catch (\Exception $e) {
                    // User doesn't have delete permission
                }
            }

            $tempRow['operate'] = $operate;
            $rows[] = $tempRow;
        }

        $bulkData['rows'] = $rows;
        return response()->json($bulkData);
    }

    public function destroy($id) {
        try {
            ResponseService::noPermissionThenSendJson('staff-delete');
            
            // Prevent deleting own account
            if (Auth::id() == $id) {
                ResponseService::errorResponse('You cannot delete your own account');
                return;
            }
            
            // Prevent deleting Super Admin users
            $user = User::withTrashed()->with('roles')->findOrFail($id);
            if ($user->roles->first()->name === 'Super Admin') {
                ResponseService::errorResponse('Super Admin users cannot be deleted');
                return;
            }
            
            $user->forceDelete();
            ResponseService::successResponse('User Delete Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "StaffController --> delete");
            ResponseService::errorResponse();
        }
    }


    public function changePassword(Request $request, $id) {
        ResponseService::noPermissionThenRedirect('staff-edit');
        $validator = Validator::make($request->all(), [
            'new_password'     => 'required|min:8',
            'confirm_password' => 'required|same:new_password'
        ]);
        if ($validator->fails()) {
            ResponseService::validationError($validator->errors()->first());
        }
        try {
            User::findOrFail($id)->update(['password' => Hash::make($request->confirm_password)]);
            ResponseService::successResponse('Password Reset Successfully');
        } catch (Throwable $th) {
            ResponseService::logErrorResponse($th, "StaffController -> changePassword");
            ResponseService::errorResponse();
        }

    }
}
