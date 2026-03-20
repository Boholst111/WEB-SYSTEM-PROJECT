@extends('emails.layout')

@section('title', $subject)

@section('content')
    <h1>{{ $subject }}</h1>
    
    <p>Hi {{ $user->first_name }},</p>
    
    <div style="padding: 20px 0;">
        {!! nl2br(e($message)) !!}
    </div>
    
    <p>Thank you for being a valued member of the Diecast Empire community!</p>
@endsection
