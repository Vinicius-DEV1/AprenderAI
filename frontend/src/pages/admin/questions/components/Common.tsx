export function TriageAction({ icon, onClick, pending, variant = 'default' }: any) {
    const variants: any = {
        'blade-orange': 'bg-orange-100 text-orange-700 hover:bg-orange-200',
        'blade-blue': 'bg-blue-100 text-blue-700 hover:bg-blue-200',
        'blade-yellow': 'bg-yellow-100 text-yellow-700 hover:bg-yellow-200',
        'blade-indigo': 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200',
        'blade-green': 'bg-green-100 text-green-700 hover:bg-green-200',
        'blade-purple': 'bg-purple-100 text-purple-700 hover:bg-purple-200',
        'blade-gray': 'bg-gray-100 text-gray-700 hover:bg-gray-200',
        'primary': 'bg-indigo-600 text-white shadow-indigo-100 hover:bg-indigo-700',
        'default': 'bg-white border border-gray-100 text-gray-700 hover:border-indigo-200'
    };

    return (
        <button
            onClick={onClick}
            disabled={pending}
            className={`px-3 py-1.5 text-[9px] rounded-lg font-black transition shadow-sm flex items-center justify-center gap-1.5 border border-transparent uppercase tracking-wider ${pending ? 'opacity-50 cursor-wait bg-gray-100' : ''} ${variants[variant]}`}
        >
            {pending ? <span className="animate-spin text-xs">⏳</span> : icon}
        </button>
    );
}

export function DifficultyBadge({ level }: { level: string }) {
    const configs: any = {
        easy: { label: 'Fácil', style: 'bg-green-50 text-green-600 border-green-100' },
        medium: { label: 'Médio', style: 'bg-yellow-50 text-yellow-600 border-yellow-100' },
        hard: { label: 'Difícil', style: 'bg-red-50 text-red-600 border-red-100' },
        default: { label: 'Indefinido', style: 'bg-gray-50 text-gray-400 border-gray-100' }
    };
    const c = configs[level] || configs.default;
    return (
        <span className={`text-[10px] font-black uppercase px-2 py-1 rounded-lg border ${c.style}`}>
            {c.label}
        </span>
    );
}
