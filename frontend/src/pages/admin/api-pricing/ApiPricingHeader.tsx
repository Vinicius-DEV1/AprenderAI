import React from 'react';

interface ApiPricingHeaderProps {
    resetCreateForm: () => void;
    setCreateModalOpen: (open: boolean) => void;
}

const ApiPricingHeader: React.FC<ApiPricingHeaderProps> = ({ resetCreateForm, setCreateModalOpen }) => {
    return (
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 className="text-3xl font-black text-slate-800 tracking-tight">Custos de API 💰</h1>
                <p className="text-slate-500 font-medium mt-1">Defina o custo (Input/Output) de cada modelo para faturamento e estimativas.</p>
            </div>
            <button
                onClick={() => { resetCreateForm(); setCreateModalOpen(true); }}
                className="px-5 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-100 flex items-center gap-2"
            >
                <span>➕</span> Novo Preço de Modelo
            </button>
        </div>
    );
};

export default ApiPricingHeader;
