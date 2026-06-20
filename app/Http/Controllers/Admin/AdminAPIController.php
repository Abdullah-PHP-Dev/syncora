<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\AdminAPIRequest;
use App\Models\Admin\AdminSetting;

class AdminAPIController extends Controller
{
    protected $adminSettingModel;

    public function __construct(AdminSetting $adminSettingModel)
    {
        $this->adminSettingModel = $adminSettingModel;
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $adminSettings =  $this->adminSettingModel->orderBy('id','desc')->paginate(50);

        return view('admin.api.index', compact('adminSettings'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AdminAPIRequest $request)
    {
        $key = $request->key;
        $value = $request->value;

        $adminSetting = set_adminSetting($key, $value);
       
        if ($adminSetting) {
            $adminSetting = adminSetting($key);
            return response()->json(['success' =>true, 'data' => $adminSetting]);
        }

        return response()->json(['success' =>false, 'message' => 'Admin setting not saved.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $key)
    {
        $adminSetting = adminSetting($key);
       
        if ($adminSetting) {
            return response()->json(['success' =>true, 'data' => $adminSetting]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AdminAPIRequest $request, string $key)
    {
        $adminSetting = set_adminSetting($key, $request->value);
       
        if ($adminSetting) {
            $adminSetting = adminSetting($key);
            return response()->json(['success' =>true, 'data' => $adminSetting]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $key)
    {
        $setting = deleteAdminSetting($key);

        if ($setting) {
            return response()->json(['success' =>true]);

        }
    }
}
