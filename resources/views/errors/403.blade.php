@extends('errors.layout')

@section('code', '403')
@section('title', 'Access denied')
@section('message', $exception?->getMessage() ?: "You don't have permission to view this page. If you think this is a mistake, please contact an administrator.")
