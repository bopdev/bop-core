<?php 

//Reject if accessed directly
defined( 'ABSPATH' ) || die( 'Our survey says: ... X.' );

if( ! class_exists( 'Bop_Object' ) ):

abstract class Bop_Object{
  
  public $id = 0;
  
  abstract protected $_meta_type;
  
  public function __construct( $id_or_data = 0 ){
    if( $id_or_data ){
      if( is_array( $id_or_data ) || is_object( $id_or_data ) ){
        $this->fill_object( (array)$id_or_data );
      }else{
        $this->load( $id );
      }
    }
    return $this;
  }
  
  public function load( $id ){
    $fields = $this->_fetch_from_db( $id );
    $this->fill_object( $fields );
    return $this;
  }
  
  public function fill_object( $data ){
    if( isset( $data['id'] ) ){
      $this->id = $data['id'];
    }
  }
  
  abstract protected function _fetch_from_db();
  
  abstract public function insert();
  
  abstract public function update();
  
  //WP has a stupid way of getting meta
  public function get_meta( $k = '', $single = true ){
    if( ! $this->id ) return false;
    
    //if no key then only false is okay
    $single = $k && $single;
    
    $m = get_metadata( $this->_meta_type, $this->id, $k, $single );
    
    if( ! $k ){
      foreach( $m as $key=>$value ){
        $m[$key] = maybe_unserialize( $value[0] );
      }
    }
    
    return $m;
  }
  
  public function update_meta( $k, $v, $prev = '' ){
    if( ! $this->id ) return false;
    return update_metadata( $this->_meta_type, $this->id, $k, $v, $prev );
  }
  
  public function add_meta( $k, $v, $unique = false ){
    if( ! $this->id ) return false;
    return add_metadata( $this->_meta_type, $this->id, $k, $v, $unique );
  }
  
  public function delete_meta( $k, $v = '' ){
    if( ! $this->id ) return false;
    return delete_metadata( $this->_meta_type, $this->id, $k, $v );
  }
  
  public function update_multi_meta( $k, $new_items ){
    $old_items = $this->get_meta( $k );
    
    //clean input before comparison
    for( $i = 0; $i < count( $new_items ); $i++ ){
      $new_items[$i] = trim( $new_items[$i] );
    }
    
    //check what's new
    $to_add = [];
    foreach( $new_items as $new_item ){
      if( ! in_array( $new_item, $old_items ) ){
        $to_add[] = $new_item;
      }
    }
    
    //replace expired with new or, if no more new, simply delete expired
    $i = 0;
    foreach( $old_items as $old_item ){
      if( ! in_array( $old_item, $new_items ) ){
        if( isset( $to_add[$i] ) ){
          $this->update_meta( $k, $to_add[$i], $old_item );
          ++$i;
        }else{
          $this->delete_meta( $k, $old_item );
        }
      }
    }
    
    //add any remaining new
    while( $i < count( $to_add ) ){
      $this->add_meta( $k, $to_add[$i] );
      ++$i;
    }
  }
}

endif; //class exists
