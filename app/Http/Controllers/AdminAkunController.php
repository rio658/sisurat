<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AdminAkunController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:255']]);
        $query = User::query()->select(['id', 'name', 'username', 'role', 'jabatan']);

        if (! empty($data['q'])) {
            $search = $data['q'];
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('jabatan', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('id')->paginate(15)->withQueryString();
        $jumlahAkun = User::count();

        return view('admin.akun.index', compact('users', 'jumlahAkun'));
    }

    public function edit(User $user)
    {
        return view('admin.akun.edit', compact('user'));
    }
}
