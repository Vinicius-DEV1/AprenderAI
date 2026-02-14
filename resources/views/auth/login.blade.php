@extends('layouts.guest')

@section('title', 'Login - AprovaAI')

@section('content')
    <div class="logo">
        <h1>AprovadoAI</h1>
        <p>Entre na sua conta</p>
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

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div class="checkbox-group">
            <input type="checkbox" id="remember" name="remember">
            <label for="remember">Lembrar de mim</label>
        </div>

        <button type="submit" class="btn">Entrar</button>
    </form>

    <div class="link">
        Não tem uma conta? <a href="{{ route('register') }}">Cadastre-se gratuitamente</a>
    </div>
@endsection