/**
 * CropEditor
 * Integrates react-cropper for image manipulation and saving coordinates.
 */
import React from 'react';
import Cropper from 'react-cropper';
import 'cropperjs/dist/cropper.css';
import { ImportImage } from './Types';

interface CropEditorProps {
  images: ImportImage[];
  cropperEls: React.MutableRefObject<Record<number, any>>;
  activeTarget: string;
  setActiveTarget: (t: string) => void;
  handleSaveCrop: (target?: string, imageId?: number) => void;
  deleteImageMutation: any;
  saving: boolean;
  apiUrl: string;
}

const CropEditor: React.FC<CropEditorProps> = ({
  images,
  cropperEls,
  activeTarget,
  setActiveTarget,
  handleSaveCrop,
  deleteImageMutation,
  saving,
  apiUrl
}) => {
  return (
    <div className="space-y-6">
      {images.map((img) => (
        <div key={img.id} className="bg-white rounded-lg shadow-sm overflow-hidden">
          <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div>
              <h3 className="text-sm font-semibold text-gray-800">🖼️ Editor de Recorte (ID: {img.id})</h3>
              <p className="text-xs text-gray-500 mt-0.5">Recorte para o <strong>Enunciado</strong> ou para uma <strong>Alternativa</strong>.</p>
            </div>
            <button
              onClick={() => {
                if (window.confirm('Remover esta imagem?')) deleteImageMutation.mutate(img.id);
              }}
              className="px-3 py-1.5 bg-red-100 text-red-700 text-xs rounded-md hover:bg-red-200 font-medium">
              🗑️ Remover
            </button>
          </div>

          <div className="p-0 bg-gray-900 flex justify-center overflow-hidden">
            <Cropper
              src={img.image_url ? `${apiUrl}/${img.image_url.replace(/^\//, '')}`.replace(/([^:])\/\//g, '$1/') : `${apiUrl}/storage/${img.path?.replace(/^\//, '').replace(/^storage\//, '')}`.replace(/([^:])\/\//g, '$1/')}
              style={{ height: 'auto', width: '100%', maxHeight: '600px' }}
              crossOrigin="anonymous"
              guides={true}
              viewMode={1}
              dragMode="move"
              autoCropArea={0.5}
              ref={(el: any) => { if (el) cropperEls.current[img.id] = el; }}
            />
          </div>

          <div className="p-5 border-t border-gray-100 space-y-4">
            <div className={`rounded-lg border-2 p-3 transition-colors ${
                activeTarget === 'statement' ? 'border-blue-400 bg-blue-50' : 'border-gray-200 bg-gray-50'
            }`}>
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-wide text-blue-700">📄 Enunciado</p>
                  <p className="text-xs text-gray-400 mt-0.5">Substitui a imagem do enunciado.</p>
                </div>
                <button
                  onClick={() => { setActiveTarget('statement'); handleSaveCrop('statement', img.id); }}
                  disabled={saving}
                  className="flex-shrink-0 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 font-bold text-xs flex items-center gap-2 transition-opacity disabled:opacity-50"
                >
                  {saving && activeTarget === 'statement' ? 'Salvando...' : '✂️ Confirmar Recorte'}
                </button>
              </div>
            </div>

            <div className={`rounded-lg border-2 p-3 transition-colors ${
                activeTarget !== 'statement' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 bg-gray-50'
            }`}>
              <p className="text-xs font-semibold uppercase tracking-wide text-indigo-700 mb-2">🔤 Alternativa Visual</p>
              <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-1.5">
                  {['A', 'B', 'C', 'D', 'E'].map(ltr => (
                    <button
                      key={ltr}
                      onClick={() => setActiveTarget(ltr)}
                      className={`w-8 h-8 rounded-md font-bold text-xs transition-all ${
                          activeTarget === ltr ? 'bg-indigo-600 text-white shadow-md' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-50'
                      }`}
                    >
                      {ltr}
                    </button>
                  ))}
                </div>
                <button
                  onClick={() => handleSaveCrop(undefined, img.id)}
                  disabled={saving}
                  className="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 font-bold text-xs transition-opacity disabled:opacity-50"
                >
                  {saving && activeTarget !== 'statement' ? 'Salvando...' : `Salvar Alt. ${activeTarget}`}
                </button>
              </div>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default CropEditor;
