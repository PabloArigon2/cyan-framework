<?php

enum Color {
    case RED;
    case BLUE;
    case YELLOW;
    case GREEN;
    case ORANGE;
}

class Elements {
    public static function RowBox(string $text, Color $color) {
        switch ($color) {
            case Color::RED:
                return '<div class="bx box-red">'.$text.'</div>';
            case Color::GREEN:
                return '<div class="bx box-green">'.$text.'</div>';
            case Color::YELLOW:
                return '<div class="bx box-yellow">'.$text.'</div>';
            case Color::ORANGE:
                return '<div class="bx box-orange">'.$text.'</div>';
            case Color::BLUE:
                return '<div class="bx box-blue">'.$text.'</div>';
                
        }
    }
}
?>
