@extends('layouts.guest')

@section('title', 'Cadastro - AprovaAI')

@section('content')
    <div class="logo">
        <h1>{{ $siteName }}</h1>
        <p>Crie sua conta gratuita</p>
    </div>

    @if ($errors->any())
        <div class="error">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="form-group">
            <label for="name">Nome completo</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required>
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="form-group">
            <label for="password_confirmation">Confirme a senha</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required>
        </div>

        <button type="submit" class="btn">Criar Conta Gratuita</button>
    </form>

    <div class="divider">
        Ao criar uma conta, você concorda com nossos Termos de Uso
    </div>

    <div class="link">
        Já tem uma conta? <a href="{{ route('login') }}">Faça login</a>
    </div>
@endsection