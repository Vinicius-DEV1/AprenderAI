# Documentação de Modificações — AprenderAI

> Registro técnico completo de todas as alterações realizadas no sistema.
> Cada seção documenta: **motivo, arquivos alterados, lógica implementada e como verificar**.

---

## 📌 Índice

1. [Seeder de Uso Realista](#1-seeder-de-uso-realista)
2. [Auditoria Asaas — Integridade de Pagamentos](#2-auditoria-asaas)
3. [Correção de Roteamento Admin Sidebar](#3-correção-de-roteamento-admin--sidebar)
4. [Importação `react-imask` e `react-cropper`](#4-imports-de-pacotes-front-end)
5. [Correção do Módulo de Importação ENEM Dev](#5-correção-do-módulo-de-importação-enem-dev)
6. [Correção do Botão "Ativar Roteamento"](#6-correção-do-botão-ativar-roteamento)
7. [Melhoria do Monitoramento VPS](#7-melhoria-do-monitoramento-vps)
8. [Fix Admin API Keys "Tela Branca"](#8-fix-admin-api-keys-tela-branca)
9. [Implementação do Motor de Simulados (Builder)](#9-implementação-do-motor-de-simulados-builder)

---

## 9. Implementação do Motor de Simulados (Builder)

**Data**: 2026-02-28
**Arquivos Principais**:
- `backend/app/Models/SimulationPreset.php`
- `backend/app/Models/SimulationRule.php`
- `backend/app/Http/Controllers/Api/Admin/AdminSimulationController.php`
- `frontend/src/pages/admin/SimulationBuilder.tsx`

### Motivo
Anteriormente, as regras de geração de simulado (ex: 90% banco / 10% IA) estavam "hardcoded" no código. Esta implementação permite que o administrador crie e edite diferentes perfis de geração de simulado (Presets) diretamente pela interface.

### O que foi feito
- **Banco de Dados**: Criadas tabelas `simulation_presets` e `simulation_rules`.
- **API**: Endpoints de CRUD implementados em `/api/v1/admin/simulations/presets`.
- **Frontend**: Criada a página "Motor de Simulados" com design premium, permitindo:
  - Definir proporção IA vs Humana via Slider.
  - Escolher curvas de dificuldade (Linear, Gauss, Progressiva).
  - Ativar/Desativar presets.
- **Integração**: Adicionado link no Sidebar do Admin sob a seção "Inteligência".
- **Dados Iniciais**: Criado `DefaultSimulationPresetsSeeder` com as configurações padrão do sistema.

### Verificação
Acesse `/admin/simulations/builder` no painel administrativo para gerenciar os motores de geração.
