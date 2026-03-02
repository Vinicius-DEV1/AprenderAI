export const TableSkeleton = ({ rows = 5, cols = 5 }: { rows?: number, cols?: number }) => (
    <div className="w-full animate-pulse">
        <div className="h-10 bg-gray-200 dark:bg-slate-700 rounded-lg mb-4 w-full"></div>
        {[...Array(rows)].map((_, i) => (
            <div key={i} className="flex gap-4 mb-3">
                {[...Array(cols)].map((_, j) => (
                    <div key={j} className="h-12 bg-gray-100 dark:bg-slate-800 rounded-xl flex-1"></div>
                ))}
            </div>
        ))}
    </div>
);

export const CardSkeleton = () => (
    <div className="bg-white dark:bg-slate-800 p-6 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 animate-pulse">
        <div className="w-12 h-12 bg-gray-200 dark:bg-slate-700 rounded-xl mb-4"></div>
        <div className="h-4 bg-gray-200 dark:bg-slate-700 rounded w-1/2 mb-2"></div>
        <div className="h-8 bg-gray-100 dark:bg-slate-800 rounded w-full"></div>
    </div>
);

export const AdminPageSkeleton = () => (
    <div className="p-4 md:p-6 space-y-6 w-full">
        <div className="flex justify-between items-end animate-pulse">
            <div className="space-y-2">
                <div className="h-8 bg-gray-200 dark:bg-slate-700 rounded w-64"></div>
                <div className="h-4 bg-gray-100 dark:bg-slate-800 rounded w-96"></div>
            </div>
            <div className="h-12 bg-indigo-100 dark:bg-indigo-900/30 rounded-xl w-40"></div>
        </div>
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
            <CardSkeleton />
            <CardSkeleton />
            <CardSkeleton />
            <CardSkeleton />
        </div>
        <TableSkeleton rows={8} />
    </div>
);
