import { Head, Link } from '@inertiajs/react';
import { Briefcase, Inbox, MoreVertical, Plus, Trash2 } from 'lucide-react';
import { useState, type ReactNode } from 'react';

import { Alert } from '@/components/ui/Alert';
import { Button, IconButton } from '@/components/ui/Button';
import { DataTable } from '@/components/ui/DataTable';
import {
    Avatar,
    Badge,
    Card,
    CardTitle,
    EmptyState,
    ProgressBar,
    Skeleton,
    StatCard,
    Timeline,
} from '@/components/ui/display';
import { Field, Input, SearchInput, Select, Textarea } from '@/components/ui/form';
import { Checkbox } from '@/components/ui/Checkbox';
import { FileInput } from '@/components/ui/FileInput';
import { RadioGroup } from '@/components/ui/RadioGroup';
import { Switch } from '@/components/ui/Switch';
import { Breadcrumbs, Pagination, Tabs } from '@/components/ui/navigation';
import { OtpInput } from '@/components/ui/OtpInput';
import { BottomSheet, ConfirmDialog, Dialog } from '@/components/ui/Dialog';
import { DropdownMenu } from '@/components/ui/DropdownMenu';
import { Tooltip } from '@/components/ui/Tooltip';
import { Popover } from '@/components/ui/Popover';
import { PhoneInput } from '@/components/ui/PhoneInput';
import { ScoreRing } from '@/components/ui/ScoreRing';
import { Stepper } from '@/components/ui/Stepper';
import { toast } from '@/components/ui/Toast';
import { PublicLayout } from '@/layouts/PublicLayout';
import { formatDate, formatDateTime, formatMoney, formatPhone } from '@/lib/format';

function Section({ id, title, children }: { id: string; title: string; children: ReactNode }) {
    return (
        <section id={id} aria-labelledby={`${id}-title`} className="border-line scroll-mt-20 border-t py-10">
            <h2 id={`${id}-title`} className="text-fg mb-6 text-xl font-bold">
                {title}
            </h2>
            {children}
        </section>
    );
}

const SWATCHES = [
    ['Kasi indigo', 'bg-kasi-indigo', '#24206B'],
    ['Action', 'bg-kasi-action', '#3B34B5'],
    ['Marigold', 'bg-kasi-marigold', '#F5B700'],
    ['Success', 'bg-kasi-green', '#0E9F8A'],
    ['Danger', 'bg-kasi-danger', '#C73E3E'],
    ['Info', 'bg-kasi-info', '#2563EB'],
] as const;

const SECTIONS = [
    'tokens',
    'buttons',
    'forms',
    'display',
    'feedback',
    'overlays',
    'navigation',
    'data',
    'formats',
    'layouts',
];

interface DemoRow {
    id: number;
    name: string;
    hub: string;
    score: number;
    status: string;
}

const ROWS: DemoRow[] = [
    { id: 1, name: 'Thandi Mabasa', hub: 'Tsutsumani', score: 9.4, status: 'Interview' },
    { id: 2, name: 'Lwazi Chauke', hub: 'Giyani Central', score: 8.1, status: 'Applied' },
    { id: 3, name: 'Nyiko Hlungwani', hub: 'Malamulele', score: 6.2, status: 'Shortlisted' },
];

export default function UiKit({ layouts }: { layouts: string[] }) {
    const [phone, setPhone] = useState('');
    const [otp, setOtp] = useState('');
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [page, setPage] = useState(1);
    const [step, setStep] = useState(1);
    const [file, setFile] = useState<File | null>(null);

    return (
        <PublicLayout>
            <Head title="UI kit" />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <Badge tone="warning">Internal - not available in production</Badge>
                <h1 className="text-fg mt-3 text-3xl font-bold tracking-tight">KasiHub UI kit</h1>
                <p className="text-fg-muted mt-2 max-w-2xl">
                    Every component and layout used across the platform. Build screens from these parts. Rules and
                    usage: docs/design-system.md.
                </p>
                <nav aria-label="UI kit sections" className="mt-6 flex flex-wrap gap-2">
                    {SECTIONS.map((section) => (
                        <a
                            key={section}
                            href={`#${section}`}
                            className="border-line bg-surface text-fg hover:bg-surface-muted rounded-full border px-3 py-1 text-sm capitalize"
                        >
                            {section}
                        </a>
                    ))}
                </nav>

                <Section id="tokens" title="Colours and type">
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {SWATCHES.map(([name, className, hex]) => (
                            <li key={name} className="rounded-card border-line bg-surface overflow-hidden border">
                                <div className={`h-16 ${className}`} />
                                <div className="p-3 text-sm">
                                    <p className="text-fg font-semibold">{name}</p>
                                    <p className="text-fg-muted">{hex}</p>
                                </div>
                            </li>
                        ))}
                    </ul>
                    <div className="mt-6 space-y-2">
                        <p className="text-fg text-4xl font-bold tracking-tight">Heading 1 - Poppins Bold</p>
                        <p className="text-fg text-2xl font-semibold">Heading 2 - Poppins Semibold</p>
                        <p className="text-fg text-base">
                            Body text - plain, short sentences that are easy to read on a small screen.
                        </p>
                        <p className="text-fg-muted text-sm">Muted text for hints and supporting details.</p>
                    </div>
                </Section>

                <Section id="buttons" title="Buttons">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button>Primary</Button>
                        <Button variant="accent">Accent</Button>
                        <Button variant="secondary">Secondary</Button>
                        <Button variant="ghost">Ghost</Button>
                        <Button variant="danger" icon={<Trash2 className="size-4" aria-hidden />}>
                            Delete
                        </Button>
                        <Button loading>Saving</Button>
                        <Button size="sm">Small</Button>
                        <Button size="lg" icon={<Plus className="size-5" aria-hidden />}>
                            Large with icon
                        </Button>
                        <IconButton label="More options">
                            <MoreVertical className="size-5" aria-hidden />
                        </IconButton>
                    </div>
                </Section>

                <Section id="forms" title="Forms">
                    <div className="grid gap-6 md:grid-cols-2">
                        <Field label="Full name" hint="As it appears on your ID" required>
                            <Input placeholder="Thandi Mabasa" />
                        </Field>
                        <Field label="Cellphone number" hint="We'll send a code to this number">
                            <PhoneInput value={phone} onChange={(value) => setPhone(value)} />
                        </Field>
                        <Field label="Village or township" error="Please choose where you live">
                            <Input defaultValue="" />
                        </Field>
                        <Field label="Highest education">
                            <Select defaultValue="12">
                                <option value="10">Grade 10</option>
                                <option value="11">Grade 11</option>
                                <option value="12">Grade 12 (Matric)</option>
                                <option value="cert">Certificate or diploma</option>
                            </Select>
                        </Field>
                        <Field label="Start date">
                            <Input type="date" />
                        </Field>
                        <Field label="Document" hint="PDF or a clear photo, up to 10 MB">
                            <FileInput
                                file={file}
                                onChange={setFile}
                                chooseLabel="Choose file"
                                photoLabel="Take a photo"
                            />
                        </Field>
                        <Field label="Search">
                            <SearchInput placeholder="Search jobs, courses or mentors" aria-label="Search" />
                        </Field>
                        <Field
                            label="Tell us about your work"
                            hint="Include piece jobs and family business - it all counts"
                            className="md:col-span-2"
                        >
                            <Textarea />
                        </Field>
                        <RadioGroup
                            legend="How far can you travel?"
                            defaultValue="20"
                            options={[
                                { value: '5', label: 'Up to 5 km', hint: 'Walking distance' },
                                { value: '20', label: 'Up to 20 km', hint: 'One taxi ride' },
                                { value: '50', label: 'Up to 50 km' },
                            ]}
                        />
                        <div className="flex flex-col gap-4">
                            <Checkbox label="I agree that KasiHub may use my details to build my CV and match me to jobs." />
                            <Switch label="Send me job alerts on WhatsApp" defaultChecked />
                            <div>
                                <p className="text-fg mb-2 text-sm font-semibold">Verification code</p>
                                <OtpInput
                                    value={otp}
                                    onChange={setOtp}
                                    onComplete={() => toast('Code entered', { tone: 'success' })}
                                />
                            </div>
                        </div>
                        <div className="md:col-span-2">
                            <Stepper
                                steps={['Consent', 'Your details', 'Your experience', 'Review CV']}
                                current={step}
                            />
                            <div className="mt-3 flex gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    onClick={() => setStep((s) => Math.max(0, s - 1))}
                                >
                                    Back
                                </Button>
                                <Button size="sm" onClick={() => setStep((s) => Math.min(3, s + 1))}>
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>
                </Section>

                <Section id="display" title="Cards and data display">
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <StatCard label="Youth registered" value="2 418" change="+28 this month" />
                        <StatCard label="CVs completed" value="2 106" change="87% completion" />
                        <StatCard label="Interviews" value="384" change="+21% vs last quarter" />
                        <StatCard label="Youth hired" value="127" change="Avg match score 8.9" emphasis />
                    </div>
                    <div className="mt-6 grid gap-4 lg:grid-cols-3">
                        <Card>
                            <CardTitle>Match scores</CardTitle>
                            <div className="mt-4 flex flex-wrap items-end gap-4">
                                <ScoreRing score={9.4} size={88} showCaption />
                                <ScoreRing score={7.6} showCaption />
                                <ScoreRing score={5.8} showCaption />
                                <ScoreRing score={3.2} size={44} />
                            </div>
                        </Card>
                        <Card>
                            <CardTitle>Badges, avatars, progress</CardTitle>
                            <div className="mt-4 flex flex-wrap gap-2">
                                <Badge>Neutral</Badge>
                                <Badge tone="primary">Learner</Badge>
                                <Badge tone="success">Verified employer</Badge>
                                <Badge tone="warning">Pending</Badge>
                                <Badge tone="danger">High risk</Badge>
                                <Badge tone="info">New</Badge>
                            </div>
                            <div className="mt-4 flex items-center gap-2">
                                <Avatar name="Thandi Mabasa" size="sm" />
                                <Avatar name="Sipho Nkuna" />
                                <Avatar name="Rhulani Baloyi" size="lg" />
                            </div>
                            <ProgressBar className="mt-4" value={64} label="Course progress" />
                        </Card>
                        <Card>
                            <CardTitle>Timeline</CardTitle>
                            <div className="mt-4">
                                <Timeline
                                    items={[
                                        { title: 'Registered at the hub', meta: 'Mar 2026', done: true },
                                        { title: 'CV created', meta: 'Mar 2026', done: true },
                                        { title: 'Course completed', meta: 'Sep 2026', done: true },
                                        { title: 'Funding application', meta: 'Next step' },
                                    ]}
                                />
                            </div>
                        </Card>
                    </div>
                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                        <EmptyState
                            icon={<Inbox className="size-8" aria-hidden />}
                            title="No applications yet"
                            description="When you apply for a job it will show here with its progress."
                            action={<Button icon={<Briefcase className="size-4" aria-hidden />}>Find jobs</Button>}
                        />
                        <Card role="status" aria-busy="true" aria-label="Loading example">
                            <Skeleton className="h-5 w-1/2" />
                            <Skeleton className="mt-3 h-4 w-full" />
                            <Skeleton className="mt-2 h-4 w-5/6" />
                            <Skeleton className="mt-6 h-11 w-32" />
                        </Card>
                    </div>
                </Section>

                <Section id="feedback" title="Feedback">
                    <div className="grid gap-3 md:grid-cols-2">
                        <Alert title="Your CV is ready">You can download it or print it at your hub.</Alert>
                        <Alert tone="success" title="Application sent">
                            Mopani Fresh Market will reply within 5 working days.
                        </Alert>
                        <Alert tone="warning" title="One document missing">
                            Upload your matric certificate to apply for funding.
                        </Alert>
                        <Alert
                            tone="danger"
                            title="We couldn't send the code"
                            action={
                                <Button size="sm" variant="secondary">
                                    Try again
                                </Button>
                            }
                        >
                            Check the number and try again in a minute.
                        </Alert>
                    </div>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <Button
                            variant="secondary"
                            onClick={() => toast('Saved', { tone: 'success', description: 'Your changes are saved.' })}
                        >
                            Show success toast
                        </Button>
                        <Button variant="secondary" onClick={() => toast('Something went wrong', { tone: 'danger' })}>
                            Show error toast
                        </Button>
                        <Button variant="danger" onClick={() => setConfirmOpen(true)}>
                            Delete job
                        </Button>
                        <ConfirmDialog
                            open={confirmOpen}
                            onOpenChange={setConfirmOpen}
                            danger
                            title="Delete this job?"
                            description="Applicants will no longer see it. This can't be undone."
                            confirmLabel="Delete job"
                            onConfirm={() => toast('Job deleted')}
                        />
                    </div>
                </Section>

                <Section id="overlays" title="Dialogs, sheets and menus">
                    <div className="flex flex-wrap items-center gap-3">
                        <Dialog
                            trigger={<Button variant="secondary">Open dialog</Button>}
                            title="Book an interview"
                            description="Choose a time that suits the candidate."
                            footer={<Button>Book interview</Button>}
                        >
                            Dialog content goes here.
                        </Dialog>
                        <BottomSheet
                            trigger={<Button variant="secondary">Open bottom sheet</Button>}
                            title="Filter jobs"
                            footer={<Button block>Show 12 jobs</Button>}
                        >
                            Filters go here. Sheets are easier to use on phones than dropdowns.
                        </BottomSheet>
                        <DropdownMenu
                            label="Job actions"
                            trigger={<Button variant="secondary">Menu</Button>}
                            items={[
                                { label: 'Edit', onSelect: () => toast('Edit') },
                                { label: 'Duplicate', onSelect: () => toast('Duplicate') },
                                { label: 'Delete', danger: true, onSelect: () => toast('Delete') },
                            ]}
                        />
                        <Tooltip content="Your score out of 10 for this job">
                            <Button variant="ghost">Hover or focus me</Button>
                        </Tooltip>
                        <Popover trigger={<Button variant="ghost">Popover</Button>}>
                            <p className="font-semibold">Why this score?</p>
                            <p className="text-fg-muted mt-1">Two years of cash handling and matric completed.</p>
                        </Popover>
                    </div>
                </Section>

                <Section id="navigation" title="Navigation">
                    <Breadcrumbs
                        items={[
                            { label: 'Admin', href: '/admin' },
                            { label: 'Hubs', href: '/admin/hubs' },
                            { label: 'Tsutsumani' },
                        ]}
                    />
                    <div className="mt-6">
                        <Tabs
                            label="Job details"
                            items={[
                                {
                                    value: 'about',
                                    label: 'About the job',
                                    content: <p className="text-fg">Duties and requirements.</p>,
                                },
                                {
                                    value: 'why',
                                    label: 'Why you match',
                                    content: <p className="text-fg">Reasons for the score.</p>,
                                },
                                {
                                    value: 'gaps',
                                    label: 'To reach 10/10',
                                    content: <p className="text-fg">Courses that close the gap.</p>,
                                },
                            ]}
                        />
                    </div>
                    <div className="mt-6 max-w-md">
                        <Pagination page={page} pages={4} onPageChange={setPage} />
                    </div>
                </Section>

                <Section id="data" title="Responsive table">
                    <p className="text-fg-muted mb-4 text-sm">
                        A table on desktop; stacked cards on phones (no sideways scrolling).
                    </p>
                    <DataTable<DemoRow>
                        caption="Candidates"
                        rows={ROWS}
                        rowKey={(row) => row.id}
                        columns={[
                            {
                                key: 'name',
                                header: 'Name',
                                cell: (row) => <span className="font-semibold">{row.name}</span>,
                            },
                            { key: 'hub', header: 'Hub', cell: (row) => row.hub, hideOnMobile: true },
                            { key: 'score', header: 'Match', cell: (row) => <ScoreRing score={row.score} size={36} /> },
                            {
                                key: 'status',
                                header: 'Status',
                                cell: (row) => <Badge tone="primary">{row.status}</Badge>,
                            },
                        ]}
                    />
                </Section>

                <Section id="formats" title="South African formats">
                    <dl className="grid gap-3 sm:grid-cols-2">
                        {[
                            ['Money', formatMoney(123456)],
                            ['Money (whole rands)', formatMoney(450000, { wholeRands: true })],
                            ['Phone', formatPhone('+27724183390')],
                            ['Date', formatDate('2026-10-07T12:30:00Z')],
                            ['Date and time (SAST)', formatDateTime('2026-10-07T12:30:00Z')],
                        ].map(([label, value]) => (
                            <div
                                key={label}
                                className="rounded-control border-line bg-surface flex justify-between border px-4 py-3"
                            >
                                <dt className="text-fg-muted text-sm">{label}</dt>
                                <dd className="text-fg font-semibold">{value}</dd>
                            </div>
                        ))}
                    </dl>
                </Section>

                <Section id="layouts" title="Layouts">
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {layouts.map((layout) => (
                            <li key={layout}>
                                <Link
                                    href={`/ui-kit/layouts/${layout}`}
                                    className="rounded-card border-line bg-surface text-fg hover:bg-surface-muted flex min-h-16 items-center justify-between border px-4 font-semibold capitalize"
                                >
                                    {layout} layout <span aria-hidden>→</span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Section>
            </div>
        </PublicLayout>
    );
}
