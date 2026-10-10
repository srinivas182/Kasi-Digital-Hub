import Image from '@tiptap/extension-image';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { EditorContent, type JSONContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
    Bold,
    Heading2,
    Heading3,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    MessageSquareQuote,
    Redo2,
    Table2,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/Button';
import { Field, Input } from '@/components/ui/form';
import { cn } from '@/lib/cn';
import { useTranslation } from '@/lib/i18n';

interface Props {
    value: JSONContent | null;
    onChange: (doc: JSONContent) => void;
    onUploadImage: (file: File, alt: string) => Promise<string | null>;
    label: string;
}

/**
 * Lesson editor (TipTap). Only the formatting KasiLearn renders safely is available; the server
 * renders from an allow-list, so anything else would be dropped anyway.
 */
export function Editor({ value, onChange, onUploadImage, label }: Props) {
    const { t } = useTranslation();
    const [pendingImage, setPendingImage] = useState<File | null>(null);
    const [alt, setAlt] = useState('');
    const [busy, setBusy] = useState(false);
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3, 4] },
                code: false,
                codeBlock: false,
                strike: false,
                underline: false,
                link: { openOnClick: false, autolink: true, protocols: ['https', 'http', 'mailto'] },
            }),
            Image.configure({ inline: false }),
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
        ],
        content: value ?? { type: 'doc', content: [{ type: 'paragraph' }] },
        immediatelyRender: false,
        editorProps: {
            attributes: {
                class: 'lesson-content min-h-80 p-4 focus:outline-none',
                'aria-label': label,
                role: 'textbox',
                'aria-multiline': 'true',
            },
        },
        onUpdate: ({ editor: e }) => onChange(e.getJSON()),
    });

    if (!editor) return null;

    const tool = (name: string, icon: React.ReactNode, active: boolean, run: () => void) => (
        <button
            type="button"
            title={name}
            aria-label={name}
            aria-pressed={active}
            onClick={run}
            className={cn(
                'text-fg grid size-10 place-items-center rounded',
                active ? 'bg-primary-soft' : 'hover:bg-surface-muted',
            )}
        >
            {icon}
        </button>
    );

    const insertImage = async () => {
        if (!pendingImage || !alt.trim()) return;
        setBusy(true);
        const url = await onUploadImage(pendingImage, alt.trim());
        setBusy(false);
        if (url) editor.chain().focus().setImage({ src: url, alt: alt.trim() }).run();
        setPendingImage(null);
        setAlt('');
    };

    return (
        <div className="border-line rounded-lg border">
            <div
                className="border-line flex flex-wrap gap-1 border-b p-1"
                role="toolbar"
                aria-label={t('learn.editor.toolbar')}
            >
                {tool(
                    t('learn.editor.h2'),
                    <Heading2 className="size-4" aria-hidden />,
                    editor.isActive('heading', { level: 2 }),
                    () => editor.chain().focus().toggleHeading({ level: 2 }).run(),
                )}
                {tool(
                    t('learn.editor.h3'),
                    <Heading3 className="size-4" aria-hidden />,
                    editor.isActive('heading', { level: 3 }),
                    () => editor.chain().focus().toggleHeading({ level: 3 }).run(),
                )}
                {tool(t('learn.editor.bold'), <Bold className="size-4" aria-hidden />, editor.isActive('bold'), () =>
                    editor.chain().focus().toggleBold().run(),
                )}
                {tool(
                    t('learn.editor.italic'),
                    <Italic className="size-4" aria-hidden />,
                    editor.isActive('italic'),
                    () => editor.chain().focus().toggleItalic().run(),
                )}
                {tool(
                    t('learn.editor.bullets'),
                    <List className="size-4" aria-hidden />,
                    editor.isActive('bulletList'),
                    () => editor.chain().focus().toggleBulletList().run(),
                )}
                {tool(
                    t('learn.editor.numbers'),
                    <ListOrdered className="size-4" aria-hidden />,
                    editor.isActive('orderedList'),
                    () => editor.chain().focus().toggleOrderedList().run(),
                )}
                {tool(
                    t('learn.editor.callout'),
                    <MessageSquareQuote className="size-4" aria-hidden />,
                    editor.isActive('blockquote'),
                    () => editor.chain().focus().toggleBlockquote().run(),
                )}
                {tool(t('learn.editor.link'), <Link2 className="size-4" aria-hidden />, editor.isActive('link'), () => {
                    const href = window.prompt(
                        t('learn.editor.link_prompt'),
                        editor.getAttributes('link').href ?? 'https://',
                    );
                    if (href === null) return;
                    if (href === '') editor.chain().focus().unsetLink().run();
                    else editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
                })}
                {tool(t('learn.editor.table'), <Table2 className="size-4" aria-hidden />, false, () =>
                    editor.chain().focus().insertTable({ rows: 3, cols: 2, withHeaderRow: true }).run(),
                )}
                <label
                    title={t('learn.editor.image')}
                    className="text-fg hover:bg-surface-muted grid size-10 cursor-pointer place-items-center rounded"
                >
                    <ImagePlus className="size-4" aria-hidden />
                    <span className="sr-only">{t('learn.editor.image')}</span>
                    <input
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        className="sr-only"
                        onChange={(e) => {
                            setPendingImage(e.target.files?.[0] ?? null);
                            e.target.value = '';
                        }}
                    />
                </label>
                {tool(t('learn.editor.undo'), <Undo2 className="size-4" aria-hidden />, false, () =>
                    editor.chain().focus().undo().run(),
                )}
                {tool(t('learn.editor.redo'), <Redo2 className="size-4" aria-hidden />, false, () =>
                    editor.chain().focus().redo().run(),
                )}
            </div>
            {pendingImage && (
                <div className="border-line bg-surface-muted flex flex-wrap items-end gap-2 border-b p-3">
                    <Field label={t('learn.editor.alt')} hint={t('learn.editor.alt_hint')} className="flex-1">
                        <Input value={alt} onChange={(e) => setAlt(e.target.value)} maxLength={300} />
                    </Field>
                    <Button type="button" size="sm" loading={busy} disabled={!alt.trim()} onClick={insertImage}>
                        {t('learn.editor.insert')}
                    </Button>
                    <Button type="button" size="sm" variant="ghost" onClick={() => setPendingImage(null)}>
                        {t('work.cancel')}
                    </Button>
                </div>
            )}
            <EditorContent editor={editor} />
        </div>
    );
}
