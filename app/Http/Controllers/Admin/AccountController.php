<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Pseudonym;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $role = $request->query('peran');
        $like = '%'.mb_strtolower($q, 'UTF-8').'%';

        $users = User::query()
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(username) LIKE ?', [$like])))
            ->when(in_array($role, ['mahasiswa', 'dpa', 'admin'], true), fn ($query) => $query->where('role', $role))
            ->with('dpa')
            ->orderBy('role')->orderBy('name')
            ->paginate(15)->withQueryString();

        return view('admin.accounts.index', [
            'users' => $users,
            'q' => $q,
            'role' => in_array($role, ['mahasiswa', 'dpa', 'admin'], true) ? $role : null,
            'totals' => User::selectRaw('role, count(*) as n')->groupBy('role')->pluck('n', 'role'),
        ]);
    }

    public function create(): View
    {
        return view('admin.accounts.form', [
            'account' => new User(['role' => 'mahasiswa', 'is_active' => true]),
            'dpas' => $this->dpas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9.\-]+$/', 'unique:users,username'],
            'email' => ['nullable', 'email', 'max:120', 'unique:users,email'],
            'role' => ['required', Rule::in(['mahasiswa', 'dpa', 'admin'])],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'dpa_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_DPA),
            ],
        ], [
            'username.regex' => 'NIM atau ID hanya boleh berisi huruf, angka, titik, dan strip.',
            'dpa_id.exists' => 'DPA yang dipilih tidak valid.',
        ]);

        $dpaId = $data['role'] === User::ROLE_STUDENT && ! empty($data['dpa_id'])
            ? (int) $data['dpa_id']
            : null;

        User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => ($data['email'] ?? null) ?: null,
            'role' => $data['role'],
            'password' => $data['password'],
            'student_ref' => $data['role'] === 'mahasiswa' ? Pseudonym::ref($data['username']) : null,
            'dpa_id' => $dpaId,
            'is_active' => true,
        ]);

        return redirect()->route('admin.akun')->with('status', 'Akun '.$data['username'].' dibuat.');
    }

    public function edit(User $user): View
    {
        return view('admin.accounts.form', [
            'account' => $user,
            'dpas' => $this->dpas($user->dpa_id),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:120', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'dpa_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('role', User::ROLE_DPA),
            ],
        ], [
            'dpa_id.exists' => 'DPA yang dipilih tidak valid.',
        ]);

        $active = $request->has('is_active') ? $request->boolean('is_active') : (bool) $user->is_active;
        if ($user->id === $request->user()->id && ! $active) {
            return back()->withErrors(['is_active' => 'Kamu tidak bisa menonaktifkan akunmu sendiri.'])->withInput();
        }

        if ($user->role === User::ROLE_STUDENT) {
            $dpaId = $request->exists('dpa_id')
                ? (! empty($data['dpa_id']) ? (int) $data['dpa_id'] : null)
                : $user->dpa_id;
        } else {
            $dpaId = null;
        }

        $email = $request->exists('email')
            ? (($data['email'] ?? null) ?: null)
            : $user->email;

        $user->fill([
            'name' => $data['name'],
            'email' => $email,
            'dpa_id' => $dpaId,
            'is_active' => $active,
        ]);
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        return redirect()->route('admin.akun')->with('status', 'Akun '.$user->username.' diperbarui.');
    }

    private function dpas(?int $includeId = null)
    {
        return User::where('role', User::ROLE_DPA)
            ->where(function ($query) use ($includeId) {
                $query->where('is_active', true);
                if ($includeId !== null) {
                    $query->orWhere('id', $includeId);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
