$u = \App\Models\User::updateOrCreate(
    ['email' => 'support@azcompservices.com'],
    ['name' => 'Allen Jenkins', 'password' => bcrypt('localdev12345'), 'role' => 'admin', 'email_verified_at' => now()]
);
\App\Models\Setting::set('dev_login_email', 'support@azcompservices.com');
\App\Models\Setting::set('dev_login_ip', '172.18.0.1,127.0.0.1');
echo "user id={$u->id} email={$u->email} role={$u->role}\n";
echo "dev_login_email=" . \App\Models\Setting::get('dev_login_email') . "\n";
echo "dev_login_ip=" . \App\Models\Setting::get('dev_login_ip') . "\n";
echo "setup_complete=" . var_export(\App\Models\Setting::get('setup_complete'), true) . "\n";
