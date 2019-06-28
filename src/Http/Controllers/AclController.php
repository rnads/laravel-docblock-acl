<?php

namespace TJGazel\LaravelDocBlockAcl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use TJGazel\LaravelDocBlockAcl\Services\AclService;
use Illuminate\Support\Facades\Config;

/** @permissionResource('ACL') */
class AclController extends Controller
{
    /**
     * @var AclService
     */
    private $service;

    public function __construct(AclService $service)
    {
        $this->service = $service;
    }

    /**
     * Display a listing of the resource.
     *
     * @permissionName('List')
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @throws \Exception
     */
    public function index(Request $request)
    {
        $this->service->permissionsSync();

        $groupModel = Config::get('acl.model.group');

        $groups = $groupModel::all();

        if ($request->ajax()) {
            return response()->json($groups);
        }

        return view('acl::index', compact(['groups']));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @permissionName('Create form')
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $permissionModel = Config::get('acl.model.permission');

        $resourcesPermissions = $permissionModel::orderBy('resource')
            ->orderBy('name')
            ->get()
            ->groupBy('resource');

        if ($request->ajax()) {
            return response()->json([$resourcesPermissions]);
        }

        $form = [
            'type' => 'create',
            'action' => route(aclPrefixRoutName() . 'store'),
            'method' => 'POST'
        ];

        return view('acl::form', compact(['form', 'resourcesPermissions']));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @permissionName('Add')
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'description' => 'max:255'
        ]);

        $permissions = $request->get('permissions');

        try {
            DB::beginTransaction();

            $groupModel = Config::get('acl.model.group');

            $group = $groupModel::create($request->only(['name', 'description']));

            if ($permissions && count($permissions) > 0) {
                $group->permissions()->attach($permissions);
            }

            DB::commit();

            if ($request->ajax()) {
                return response()->json([], 201);
            }

            return redirect(route(aclPrefixRoutName() . 'index'), 201)
                ->with('acl-success', __('acl::view.created'));
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('acl-error', $e->getMessage());
        }
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @permissionName('Edit form')
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request, $id)
    {
        $groupModel = Config::get('acl.model.group');
        $permissionModel = Config::get('acl.model.permission');

        $group = $groupModel::findOrFail($id)->load('permissions');

        $resourcesPermissions = $permissionModel::orderBy('resource')
            ->orderBy('name')
            ->get()
            ->groupBy('resource');

        if ($request->ajax()) {
            return response()->json([$group, $resourcesPermissions]);
        }

        $form = [
            'type' => 'edit',
            'action' => route(aclPrefixRoutName() . 'update', ['id' => $group->id]),
            'method' => 'PUT'
        ];

        return view('acl::form', compact(['form', 'group', 'resourcesPermissions']));
    }

    /**
     * Update the specified resource in storage.
     *
     * @permissionName('Update')
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|max:255',
            'description' => 'max:255'
        ]);

        try {
            DB::beginTransaction();

            $groupModel = Config::get('acl.model.group');

            $group = $groupModel::findOrfail($id);

            $group->update($request->only(['name', 'description']));

            $group->permissions()->sync($request->get('permissions'));

            DB::commit();

            if ($request->ajax()) {
                return response()->json([], 201);
            }

            return redirect(route(aclPrefixRoutName() . 'index'), 201)
                ->with('acl-success', __('acl::view.updated'));
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('acl-error', $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @permissionName('Delete')
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $groupModel = Config::get('acl.model.group');

            $group = $groupModel::findOrfail($id);

            if ($request->has('group_new_assoc')) {
                if ($request->get('group_new_assoc') != $group->id) {
                    foreach ($group->users as $user) {
                        $user->group_id = $request->get('group_new_assoc');
                        $user->save();
                    }
                } else {
                    throw new \Exception(__('acl::view.equal_assoc'));
                }
            }

            $group->permissions()->detach();

            $group->delete();

            DB::commit();

            if ($request->ajax()) {
                return response()->json([], 201);
            }

            return redirect(route(aclPrefixRoutName() . 'index'), 201)
                ->with('acl-success', __('acl::view.deleted'));
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('acl-error', $e->getMessage());
        }
    }
}
