@extends('layouts.app')

@section('content')
  <div class="container">
    <h1>Liste des utilisateurs</h1>
    @include('partials.users') <!-- Inclusion de la vue -->
  </div>
@endsection
