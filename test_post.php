<?php

$url = 'http://localhost:8000/api/v1/super-admin/block-admins';

// A super admin token might be needed!
// Wait, the API has auth middleware! 
// Let's check if the route is protected. Yes it is, /api/v1/super-admin/*
// That's why I need the token. I can't easily simulate it unless I generate a token.

