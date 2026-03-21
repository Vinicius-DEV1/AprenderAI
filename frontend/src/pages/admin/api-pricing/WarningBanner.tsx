import React from 'react';

const WarningBanner: React.FC = () => {
    return (
        <div className="bg-amber-50 border-l-4 border-amber-400 p-4 rounded-r-xl flex items-start gap-3 shadow-sm">
            <span className="text-amber-500 text-lg mt-0.5">⚠️</span>
            <div>
                <p className="text-sm font-bold text-amber-800">Impacto nos Cálculos de Custo</p>
                <p className="text-sm text-amber-700 mt-0.5">
                    As alterações nos preços aplicam-se imediatamente no cálculo das <strong>futuras requisições</strong>. 
                    Modelos que não possuem preço definido aqui cobrarão <strong className="text-red-600">$ 0.00</strong> por padrão. 
                    Os valores são expressos em <strong>USD por 1.000.000 tokens</strong>.
                </p>
            </div>
        </div>
    );
};

export default WarningBanner;
