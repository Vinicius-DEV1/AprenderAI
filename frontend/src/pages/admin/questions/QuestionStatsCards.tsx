export default function QuestionStatsCards({ stats, onOrgClick }: { stats: any[], onOrgClick: (org: string) => void }) {
    return (
        <div className="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-5 gap-6">
            {stats.map((s, i) => (
                <div
                    key={i}
                    onClick={() => s.org && onOrgClick(s.org)}
                    className={`bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex flex-col justify-between group hover:shadow-md transition ${s.org ? 'cursor-pointer hover:border-indigo-200 hover:bg-indigo-50/30' : ''}`}
                >
                    <span className="text-xs font-black text-gray-400 uppercase tracking-widest flex items-center gap-1.5">
                        {s.label}
                        {s.org && <span className="opacity-0 group-hover:opacity-100 transition text-indigo-400 text-[9px]">• Ver distribuição 🔍</span>}
                    </span>
                    <div className="flex items-end justify-between mt-4">
                        <span className={`text-4xl font-black text-${s.color}-600`}>{s.value}</span>
                        <span className={`w-8 h-8 rounded-lg bg-${s.color}-50 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition`}>
                            {s.org ? '🔍' : (s.color === 'emerald' ? '✅' : '📈')}
                        </span>
                    </div>
                </div>
            ))}
        </div>
    );
}
