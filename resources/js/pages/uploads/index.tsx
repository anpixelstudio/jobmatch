import { Form, Head, Link } from '@inertiajs/react';
import { FileUp, Plus } from 'lucide-react';
import { useMemo, useState } from 'react';
import { FieldSelect } from '@/components/field-select';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Job = {
    id: number;
    title: string;
    department: string | null;
    criteria_text: string | null;
    required_skills: string[] | null;
};

type QueueItem = {
    id: number;
    name: string;
    original_filename: string | null;
    status: string;
    category: string | null;
    review_status: string;
    created_at: string | null;
};

type Props = {
    jobs: Job[];
    queue: QueueItem[];
};

export default function UploadsIndex({ jobs, queue }: Props) {
    const [skills, setSkills] = useState(
        'Recruitment Screening, MS Excel, UU Ketenagakerjaan',
    );
    const [jobId, setJobId] = useState('');

    const jobOptions = useMemo(
        () => [
            { value: '', label: 'Opsional — tanpa lowongan' },
            ...jobs.map((j) => ({ value: String(j.id), label: j.title })),
        ],
        [jobs],
    );

    return (
        <>
            <Head title="Unggah CV" />
            <div className="mx-auto flex w-full max-w-7xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Unggah CV & Lowongan"
                    description="Atur parameter job, unggah berkas CV, lalu matching lewat chatbot."
                    actions={
                        <Button
                            asChild
                            className="bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                        >
                            <Link href="/chat">Lanjut ke Chatbot</Link>
                        </Button>
                    }
                />

                <div className="grid gap-4 lg:grid-cols-12">
                    <Card className="border-border/70 shadow-none lg:col-span-4">
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Parameter lowongan</CardTitle>
                            <CardDescription>
                                Dipakai sebagai konteks tambahan saat chat
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Form action="/jobs" method="post" className="space-y-3">
                                <div className="space-y-1.5">
                                    <Label htmlFor="title">Posisi</Label>
                                    <Input
                                        id="title"
                                        name="title"
                                        defaultValue="HR Assistant"
                                        required
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="department">Departemen</Label>
                                    <Input
                                        id="department"
                                        name="department"
                                        defaultValue="Human Resources"
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="criteria_text">Persyaratan</Label>
                                    <textarea
                                        id="criteria_text"
                                        name="criteria_text"
                                        rows={4}
                                        className="flex min-h-[96px] w-full rounded-lg border border-border/70 bg-background px-3 py-2 text-sm shadow-none outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        defaultValue="Minimal S1 Psikologi/Manajemen SDM. Pengalaman min 2 tahun HR, Excel advanced, UU Ketenagakerjaan."
                                    />
                                </div>
                                <div className="space-y-1.5">
                                    <Label htmlFor="skills">Skill wajib (pisah koma)</Label>
                                    <Input
                                        id="skills"
                                        value={skills}
                                        onChange={(e) => setSkills(e.target.value)}
                                    />
                                    {skills
                                        .split(',')
                                        .map((s) => s.trim())
                                        .filter(Boolean)
                                        .map((s) => (
                                            <input
                                                key={s}
                                                type="hidden"
                                                name="required_skills[]"
                                                value={s}
                                            />
                                        ))}
                                </div>
                                <Button
                                    type="submit"
                                    className="w-full bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                                >
                                    <Plus className="size-4" />
                                    Simpan lowongan
                                </Button>
                            </Form>

                            {jobs.length > 0 ? (
                                <div className="mt-5 space-y-2 border-t border-border/60 pt-4">
                                    <p className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                        Tersimpan
                                    </p>
                                    {jobs.map((j) => (
                                        <div
                                            key={j.id}
                                            className="rounded-lg border border-border/70 px-3 py-2 text-xs"
                                        >
                                            <p className="font-medium">{j.title}</p>
                                            <p className="text-muted-foreground">
                                                {j.department || '—'}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>

                    <div className="space-y-4 lg:col-span-8">
                        <Card className="border-border/70 shadow-none">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Unggah file CV</CardTitle>
                                <CardDescription>
                                    PDF, DOCX, atau TXT — maks 20MB
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Form
                                    action="/uploads"
                                    method="post"
                                    encType="multipart/form-data"
                                    className="space-y-4"
                                >
                                    <label
                                        htmlFor="file"
                                        className="flex cursor-pointer flex-col items-center justify-center rounded-xl border border-dashed border-teal-700/30 bg-teal-700/[0.03] px-6 py-10 text-center transition hover:bg-teal-700/[0.06]"
                                    >
                                        <FileUp className="mb-3 size-8 text-teal-800 dark:text-teal-300" />
                                        <span className="text-sm font-medium">
                                            Pilih berkas dari komputer
                                        </span>
                                        <span className="mt-1 text-xs text-muted-foreground">
                                            atau drop file di area ini
                                        </span>
                                        <Input
                                            id="file"
                                            name="file"
                                            type="file"
                                            accept=".pdf,.doc,.docx,.txt"
                                            required
                                            className="mx-auto mt-4 max-w-xs border-0 bg-transparent p-0 shadow-none file:mr-3 file:rounded-md file:border-0 file:bg-teal-800 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-white"
                                        />
                                    </label>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="job_opening_id">Lowongan</Label>
                                            <FieldSelect
                                                name="job_opening_id"
                                                value={jobId}
                                                onValueChange={setJobId}
                                                options={jobOptions}
                                                placeholder="Pilih lowongan"
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label htmlFor="category">Kategori</Label>
                                            <Input
                                                id="category"
                                                name="category"
                                                placeholder="HR / INFORMATION-TECHNOLOGY"
                                                className="border-border/70 shadow-none"
                                            />
                                        </div>
                                    </div>
                                    <Button
                                        type="submit"
                                        className="bg-teal-800 hover:bg-teal-700 dark:bg-teal-600"
                                    >
                                        Unggah & indeks CV
                                    </Button>
                                </Form>
                            </CardContent>
                        </Card>

                        <Card className="border-border/70 shadow-none">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-base">Antrean unggahan</CardTitle>
                                <CardDescription>
                                    Status parse & kesiapan matching
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {queue.length === 0 ? (
                                    <p className="py-6 text-center text-sm text-muted-foreground">
                                        Belum ada unggahan.
                                    </p>
                                ) : (
                                    queue.map((item) => (
                                        <div
                                            key={item.id}
                                            className="flex items-center justify-between gap-3 rounded-lg border border-border/70 px-3 py-2.5"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium">
                                                    {item.original_filename || item.name}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {item.category || '—'} · {item.created_at}
                                                </p>
                                            </div>
                                            <div className="flex shrink-0 items-center gap-2">
                                                <Badge variant="secondary">{item.status}</Badge>
                                                <Link
                                                    href={`/candidates/${item.id}`}
                                                    className="text-xs font-medium text-teal-800 hover:underline dark:text-teal-300"
                                                >
                                                    Detail
                                                </Link>
                                            </div>
                                        </div>
                                    ))
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}

UploadsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Unggah CV', href: '/uploads' },
    ],
};
