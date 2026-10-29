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

        $jobs = [
            [
                'title' => 'HR Assistant',
                'department' => 'Human Resources',
                'description' => 'Membantu proses rekrutmen dan administrasi HR.',
                'criteria_text' => 'Minimal S1 Psikologi/Manajemen SDM. Pengalaman min 2 tahun mengelola administrasi HR, rekrutmen awal, Microsoft Office/Excel advanced, dan pemahaman UU Ketenagakerjaan.',
                'required_skills' => ['Recruitment Screening', 'MS Excel', 'UU Ketenagakerjaan'],
            ],
            [
                'title' => 'Senior Data Analyst',
                'department' => 'Technology & Data',
                'description' => 'Analisis data untuk keputusan bisnis.',
                'criteria_text' => 'Pengalaman 3+ tahun data analyst, kuat di Python dan SQL, visualisasi data.',
                'required_skills' => ['Python', 'SQL', 'Data Analysis'],
            ],
            [
                'title' => 'IT Support Specialist',
                'department' => 'Information Technology',
                'description' => 'Support infrastruktur dan troubleshooting aplikasi internal.',
                'criteria_text' => 'Pengalaman IT support/helpdesk, networking dasar, Windows/Linux, ticketing system. Prefer sertifikasi CompTIA/Network+.',
                'required_skills' => ['IT Support', 'Networking', 'Troubleshooting'],
            ],
            [
                'title' => 'Software Engineer',
                'department' => 'Engineering',
                'description' => 'Membangun dan merawat layanan backend/frontend.',
                'criteria_text' => 'Minimal 2 tahun software engineering, mahir satu stack modern (PHP/Laravel, Node, atau Python), Git, unit testing.',
                'required_skills' => ['Software Development', 'Git', 'API'],
            ],
            [
                'title' => 'Finance Analyst',
                'department' => 'Finance',
                'description' => 'Analisis laporan keuangan dan forecasting.',
                'criteria_text' => 'S1 Akuntansi/Keuangan, pengalaman financial analysis, Excel advanced, familiar IFRS/PSAK.',
                'required_skills' => ['Financial Analysis', 'Excel', 'Reporting'],
            ],
            [
                'title' => 'Accountant',
                'department' => 'Finance',
                'description' => 'Pembukuan harian, rekonsiliasi, dan closing bulanan.',
                'criteria_text' => 'S1 Akuntansi, pengalaman general ledger/AP/AR, paham pajak dasar, teliti.',
                'required_skills' => ['Accounting', 'Bookkeeping', 'Taxation'],
            ],
            [
                'title' => 'Sales Executive',
                'department' => 'Sales',
                'description' => 'Mencapai target penjualan B2B/B2C dan menjaga pipeline.',
                'criteria_text' => 'Pengalaman sales 2+ tahun, komunikasi kuat, CRM, negosiasi, target oriented.',
                'required_skills' => ['Sales', 'CRM', 'Negotiation'],
            ],
            [
                'title' => 'Business Development Manager',
                'department' => 'Business Development',
                'description' => 'Mencari peluang partnership dan ekspansi pasar.',
                'criteria_text' => 'Pengalaman BD/partnership, riset pasar, proposal writing, presentasi ke stakeholder.',
                'required_skills' => ['Business Development', 'Partnership', 'Market Research'],
            ],
            [
                'title' => 'Legal Counsel',
                'department' => 'Legal',
                'description' => 'Review kontrak dan advis hukum operasional.',
                'criteria_text' => 'Sarjana Hukum, pengalaman corporate legal/kontrak, litasi dasar, bahasa Inggris legal.',
                'required_skills' => ['Contract Review', 'Corporate Law', 'Compliance'],
            ],
            [
                'title' => 'Digital Marketing Specialist',
                'department' => 'Digital Media',
                'description' => 'Kelola campaign digital dan performa channel.',
                'criteria_text' => 'Pengalaman digital marketing, Google Ads/Meta Ads, analytics, content planning.',
                'required_skills' => ['Digital Marketing', 'Google Ads', 'Analytics'],
            ],
            [
                'title' => 'UI/UX Designer',
                'department' => 'Design',
                'description' => 'Desain produk digital end-to-end.',
                'criteria_text' => 'Portfolio UI/UX, Figma, research dasar, design system, kolaborasi dengan engineering.',
                'required_skills' => ['UI/UX', 'Figma', 'Design System'],
            ],
            [
                'title' => 'Healthcare Administrator',
                'department' => 'Healthcare',
                'description' => 'Administrasi operasional fasilitas kesehatan.',
                'criteria_text' => 'Pengalaman administrasi klinik/rumah sakit, rekam medis, service excellence.',
                'required_skills' => ['Healthcare Admin', 'Medical Records', 'Patient Service'],
            ],
        ];

        foreach ($jobs as $job) {
            JobOpening::query()->updateOrCreate(
                ['title' => $job['title']],
                [
                    ...$job,
                    'status' => 'open',
                    'created_by' => $user->id,
                ]
            );
        }
    }
}
