export function ChatTypingBubble({ label = 'Aira sedang mengetik' }: { label?: string }) {
    return (
        <div className="flex justify-start" role="status" aria-live="polite">
            <div className="rounded-2xl rounded-bl-md bg-muted/35 px-4 py-3 ring-1 ring-teal-900/6 dark:ring-teal-400/10">
                <div className="flex items-center gap-2.5">
                    <span className="flex items-center gap-1" aria-hidden>
                        <span
                            className="size-1.5 animate-bounce rounded-full bg-teal-700 dark:bg-teal-400"
                            style={{ animationDelay: '0ms' }}
                        />
                        <span
                            className="size-1.5 animate-bounce rounded-full bg-teal-700 dark:bg-teal-400"
                            style={{ animationDelay: '150ms' }}
                        />
                        <span
                            className="size-1.5 animate-bounce rounded-full bg-teal-700 dark:bg-teal-400"
                            style={{ animationDelay: '300ms' }}
                        />
                    </span>
                    <span className="text-xs text-muted-foreground">{label}</span>
                </div>
            </div>
        </div>
    );
}
