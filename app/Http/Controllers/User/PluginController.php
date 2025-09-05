<?php

namespace App\Http\Controllers\User;

use DB;
use App\Http\Controllers\Controller as BaseController;
use App\Http\Requests\StoreWhatsappSettings;
use App\Helpers\CustomHelper;
use App\Http\Requests\StoreWhatsappProfile;
use App\Models\Addon;
use App\Models\Contact;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Template;
use App\Services\ContactFieldService;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Validator;

class PluginController extends BaseController
{
    public function index(Request $request){
        if ($request->isMethod('get')) {
            $data['title'] = __('Plugin Settings');
            $data['modules'] = Addon::get();
            
            return Inertia::render('User/Settings/Plugins/Index', $data);
        }
    }
}