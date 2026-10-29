<?php

namespace Database\Seeders;

use App\Models\JobOpening;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->where('email', 'hr@aira.test')->update(['email' => 'hr@jobmatch.test']);

        $user = User::query()->updateOrCreate(
            ['email' => 'hr@jobmatch.test'],
            [
                'name' => 'Siti Rahmawati',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        JobOpening::query()->updateOrCreate(
            ['title' => 'HR Assistant'],
            [
                'department' => 'Human Resources',
                'description' => 'Membantu proses rekrutmen dan administrasi HR.',
                'criteria_text' => 'Minimal S1 Psikologi/Manajemen SDM. Pengalaman min 2 tahun mengelola administrasi HR, rekrutmen awal, Microsoft Office/Excel advanced, dan pemahaman UU Ketenagakerjaan.',
                'required_skills' => ['Recruitment Screening', 'MS Excel', 'UU Ketenagakerjaan'],
                'status' => 'open',
                'created_by' => $user->id,
            ]
        );

        JobOpening::query()->updateOrCreate(
            ['title' => 'Senior Data Analyst'],
            [
                'department' => 'Technology & Data',
                'description' => 'Analisis data untuk keputusan bisnis.',
                'criteria_text' => 'Pengalaman 3+ tahun data analyst, kuat di Python dan SQL, visualisasi data.',
                'required_skills' => ['Python', 'SQL', 'Data Analysis'],
                'status' => 'open',
                'created_by' => $user->id,
            ]
        );
    }
}
