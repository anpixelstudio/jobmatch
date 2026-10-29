export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-teal-700 text-sm font-bold text-white shadow-sm">
                A
            </div>
            <div className="ml-2 grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold tracking-tight">
                    Jobmatch
                </span>
                <span className="truncate text-[11px] text-muted-foreground group-data-[collapsible=icon]:hidden">
                    Resume Analyzer
                </span>
            </div>
        </>
    );
}
